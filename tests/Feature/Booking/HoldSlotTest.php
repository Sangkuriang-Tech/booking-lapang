<?php

use App\Actions\Booking\HoldSlot;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    Cache::flush();

    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('requires authenticated customer to hold a slot and ignores client-supplied price or status', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer = User::query()->where('email', 'customer2@example.test')->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();

    $nextMonday = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::MONDAY);
    $startsAt = $nextMonday->setTime(16, 0)->utc();
    $endsAt = $nextMonday->setTime(18, 0)->utc();

    $payload = [
        'court_ulid' => $court->ulid,
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
        'customer_name' => 'Budi Santoso',
        'customer_phone' => '0812-3456-7890',
        'payment_mode' => Booking::PAYMENT_MODE_DP,
        'idempotency_key' => 'idem-key-001',
        // Tampered fields (Threat T4):
        'price_total' => 1000,
        'status' => Booking::STATUS_CONFIRMED,
    ];

    $this->post('/bookings', $payload)->assertRedirect(route('auth.google.redirect'));
    $this->actingAs($staff)->post('/bookings', $payload)->assertForbidden();

    $response = $this->actingAs($customer)->post('/bookings', $payload);
    $response->assertRedirect();

    $booking = Booking::withoutGlobalScopes()
        ->where('customer_id', $customer->id)
        ->where('idempotency_key', 'idem-key-001')
        ->firstOrFail();

    // Weekday 16:00-17:00 (250k) + 17:00-18:00 (350k) = 600k, DP 30% = 180k
    expect($booking->status)->toBe(Booking::STATUS_HELD)
        ->and($booking->price_total)->toBe(600000)
        ->and($booking->dp_required)->toBe(180000)
        ->and($booking->customer_phone)->toBe('6281234567890');

    // Encrypted at rest in PostgreSQL raw column
    $rawPhone = DB::table('bookings')->where('id', $booking->id)->value('customer_phone');
    expect($rawPhone)->not->toBe('6281234567890');

    // Idempotency: repeating the same key returns the exact same booking without creating a second one
    $this->actingAs($customer)->post('/bookings', $payload)->assertRedirect();

    expect(Booking::withoutGlobalScopes()->where('idempotency_key', 'idem-key-001')->count())->toBe(1);
});

it('enforces max active holds per user and per subnet for accounts without completed bookings (Threat T2)', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();

    // customer1 already has 1 seeded held booking and 0 completed bookings
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $holdSlot = app(HoldSlot::class);
    $nextTuesday = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::TUESDAY);

    // 2nd active hold succeeds (MAX_ACTIVE_HOLDS_PER_USER = 2)
    $holdSlot->execute($customer1, $court, $nextTuesday->setTime(10, 0)->utc(), $nextTuesday->setTime(11, 0)->utc(), [
        'ip' => '203.0.113.10',
    ]);

    // 3rd active hold for same user fails
    expect(fn () => $holdSlot->execute($customer1, $court, $nextTuesday->setTime(11, 0)->utc(), $nextTuesday->setTime(12, 0)->utc(), [
        'ip' => '203.0.113.10',
    ]))->toThrow(ValidationException::class);

    // New customer accounts from the same /24 subnet without completed bookings hit subnet limit (3)
    $freshCustomerA = User::query()->create([
        'role' => User::ROLE_CUSTOMER,
        'name' => 'Fresh A',
        'email' => 'fresh-a@example.test',
        'google_id' => 'google-fresh-a',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $freshCustomerB = User::query()->create([
        'role' => User::ROLE_CUSTOMER,
        'name' => 'Fresh B',
        'email' => 'fresh-b@example.test',
        'google_id' => 'google-fresh-b',
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $holdSlot->execute($freshCustomerA, $court, $nextTuesday->setTime(12, 0)->utc(), $nextTuesday->setTime(13, 0)->utc(), [
        'ip' => '203.0.113.21',
    ]);
    $holdSlot->execute($freshCustomerA, $court, $nextTuesday->setTime(13, 0)->utc(), $nextTuesday->setTime(14, 0)->utc(), [
        'ip' => '203.0.113.22',
    ]);

    // Subnet 203.0.113.0/24 now has 3 active holds (1 from customer1 + 2 from freshCustomerA)
    expect(fn () => $holdSlot->execute($freshCustomerB, $court, $nextTuesday->setTime(14, 0)->utc(), $nextTuesday->setTime(15, 0)->utc(), [
        'ip' => '203.0.113.99',
    ]))->toThrow(ValidationException::class);
});
