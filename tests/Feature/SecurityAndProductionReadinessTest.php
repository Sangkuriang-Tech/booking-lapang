<?php

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use App\Support\VenueContext;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('serves strict security headers on public and dashboard routes without unsafe-eval', function (): void {
    $publicUrls = ['/', '/venues', '/venue/antic-futsal-center-contoh'];

    foreach ($publicUrls as $url) {
        $response = $this->get($url);

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        expect($csp)
            ->toContain("default-src 'self'")
            ->toContain("frame-ancestors 'none'")
            ->toContain("object-src 'none'")
            ->not->toContain('unsafe-eval');
    }

    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();

    $dashboardResponse = $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->get('https://localhost/dashboard');

    $dashboardResponse->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('blocks cross-role and cross-tenant idor attempts across bookings, proofs, qris, settings, and admin routes', function (): void {
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();
    $owner1 = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $staff1 = User::query()->where('email', 'staff1@example.test')->firstOrFail();

    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venueGrace = Venue::query()->where('slug', 'venue-masa-tenggang-contoh')->firstOrFail();

    $customer1Booking = Booking::withoutGlobalScopes()
        ->where('customer_id', $customer1->id)
        ->firstOrFail();

    // Customer 2 cannot view Customer 1's booking
    $this->actingAs($customer2)
        ->get("/bookings/{$customer1Booking->ulid}")
        ->assertForbidden();

    // Customer 2 cannot view Customer 1's payment proof
    Storage::fake('local');
    $proofPayment = Payment::withoutGlobalScopes()
        ->where('booking_id', $customer1Booking->id)
        ->whereNotNull('proof_path')
        ->first();

    if ($proofPayment === null) {
        $proofPayment = Payment::query()->create([
            'ulid' => (string) Str::ulid(),
            'booking_id' => $customer1Booking->id,
            'venue_id' => $customer1Booking->venue_id,
            'kind' => Payment::KIND_DP,
            'method' => Payment::METHOD_TRANSFER,
            'amount' => $customer1Booking->dp_required ?: 50000,
            'status' => Payment::STATUS_PENDING,
            'proof_path' => 'payment-proofs/test-proof.enc',
        ]);
    }

    $this->actingAs($customer2)
        ->get("/payments/{$proofPayment->ulid}/proof")
        ->assertForbidden();

    // Staff/Owner of Venue 1 cannot access QRIS of another owner's venue
    $graceQris = VenuePaymentAccount::withoutGlobalScopes()
        ->where('venue_id', $venueGrace->id)
        ->first();

    if ($graceQris === null) {
        $graceQris = VenuePaymentAccount::withoutGlobalScopes()->create([
            'venue_id' => $venueGrace->id,
            'kind' => VenuePaymentAccount::KIND_QRIS,
            'bank_name' => 'QRIS',
            'account_holder' => 'Venue Grace',
            'qris_image_path' => 'venue-qris/grace-qris.png',
            'is_active' => true,
            'visible_from' => now()->subDay(),
            'created_by' => $venueGrace->owner_id,
        ]);
    }

    $this->actingAs($owner1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->get("/venues/{$venueGrace->ulid}/payment-accounts/{$graceQris->id}/qris")
        ->assertForbidden();

    // Regular staff (non-owner) cannot update venue settings
    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->put('/dashboard/settings/venue', [
            'name' => 'Hacked Venue Name',
            'address' => 'Alamat Baru',
            'city' => 'Jakarta',
            'timezone' => 'Asia/Jakarta',
            'dp_percent' => 30,
            'hold_minutes' => 15,
            'cancel_min_hours' => 24,
            'reupload_minutes' => 30,
            'is_published' => true,
        ])
        ->assertForbidden();

    // Customer and Owner cannot access /admin routes
    $this->actingAs($customer1)->get('/admin')->assertForbidden();
    $this->actingAs($owner1)->get('/admin')->assertForbidden();
});

it('rejects mass assignment of sensitive columns and hides encrypted fields from serialization', function (): void {
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->where('is_active', true)
        ->firstOrFail();

    $nextMonday = CarbonImmutable::now($venue->timezone)->next(CarbonImmutable::MONDAY);
    $startsAt = $nextMonday->setTime(10, 0)->utc();
    $endsAt = $nextMonday->setTime(11, 0)->utc();
    $idempotencyKey = (string) Str::uuid();

    $response = $this->actingAs($customer)->post('/bookings', [
        'court_ulid' => $court->ulid,
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'payment_mode' => Booking::PAYMENT_MODE_DP,
        'customer_name' => 'Pembooking Aman',
        'customer_phone' => '081234567890',
        'idempotency_key' => $idempotencyKey,
        // Attempted mass-assignment / privilege tampering
        'role' => User::ROLE_SUPERADMIN,
        'is_active' => false,
        'status' => Booking::STATUS_CONFIRMED,
        'price_total' => 1,
        'paid_total' => 9999999,
    ]);

    $response->assertRedirect();

    $booking = Booking::withoutGlobalScopes()
        ->where('customer_id', $customer->id)
        ->where('idempotency_key', $idempotencyKey)
        ->firstOrFail();

    expect($booking->status)->toBe(Booking::STATUS_HELD)
        ->and($booking->paid_total)->toBe(0)
        ->and($booking->price_total)->toBeGreaterThan(1);

    // Attempted visible_from bypass on 2nd+ payment account
    Mail::fake();
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'Mandiri',
            'account_number' => '1234567890123',
            'account_holder' => 'Pemilik Contoh',
            'current_password' => 'PasswordDev123!',
            'visible_from' => now()->subYear()->toIso8601String(),
        ])
        ->assertRedirect();

    $latestAccount = VenuePaymentAccount::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->latest('id')
        ->firstOrFail();

    expect($latestAccount->visible_from->greaterThan(now()->addHours(23)))->toBeTrue();

    // Serialization hides sensitive fields
    $userArray = $customer->fresh()->toArray();
    $bookingArray = $booking->toArray();
    $accountArray = $latestAccount->toArray();

    expect($userArray)->not->toHaveKeys(['phone', 'google_id', 'password', 'remember_token'])
        ->and($bookingArray)->not->toHaveKeys(['customer_phone', 'qr_token_hash', 'qr_token_enc'])
        ->and($accountArray)->not->toHaveKey('account_number');
});

it('enforces append-only immutability on booking_status_logs and audit_logs at the database level', function (): void {
    $statusLog = BookingStatusLog::query()->first();
    if ($statusLog === null) {
        $booking = Booking::withoutGlobalScopes()->firstOrFail();
        $statusLog = BookingStatusLog::query()->create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $booking->status,
            'actor_kind' => 'system',
            'reason' => 'Immutable test seed',
            'created_at' => now(),
        ]);
    }

    $auditLog = AuditLog::query()->first();
    if ($auditLog === null) {
        $auditLog = AuditLog::query()->create([
            'action' => 'test.immutability',
            'created_at' => now(),
        ]);
    }

    expect(fn () => DB::table('booking_status_logs')->where('id', $statusLog->id)->update(['reason' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('booking_status_logs')->where('id', $statusLog->id)->delete())
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_logs')->where('id', $auditLog->id)->update(['action' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_logs')->where('id', $auditLog->id)->delete())
        ->toThrow(QueryException::class);
});

it('rate limits sensitive endpoints such as check-in lookup and payment account re-auth', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();

    app(VenueContext::class)->set($venue->id);

    for ($i = 0; $i < 10; $i++) {
        $this->actingAs($owner)
            ->withSession(['active_venue_id' => $venue->id])
            ->post('/dashboard/payment-accounts', [
                'kind' => VenuePaymentAccount::KIND_BANK,
                'bank_name' => 'BCA',
                'account_number' => '1112223334',
                'account_holder' => 'Owner',
                'current_password' => 'WrongPassword123!',
            ]);
    }

    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/payment-accounts', [
            'kind' => VenuePaymentAccount::KIND_BANK,
            'bank_name' => 'BCA',
            'account_number' => '1112223334',
            'account_holder' => 'Owner',
            'current_password' => 'WrongPassword123!',
        ])
        ->assertStatus(429);
});
