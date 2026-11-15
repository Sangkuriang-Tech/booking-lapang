<?php

use App\Actions\Booking\HoldSlot;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PaymentActionRequiredNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

function createPendingValidationBooking(Venue $venue, Court $court, User $customer, int $daysAhead = 3, string $proofHash = ''): Booking
{
    $day = CarbonImmutable::now($venue->timezone ?: 'Asia/Jakarta')->addDays($daysAhead)->startOfDay();
    $booking = app(HoldSlot::class)->execute(
        $customer,
        $court,
        $day->setTime(19, 0)->utc(),
        $day->setTime(20, 0)->utc(),
    );

    $booking->update([
        'status' => Booking::STATUS_AWAITING_VALIDATION,
        'hold_expires_at' => null,
    ]);

    Payment::withoutGlobalScopes()->create([
        'booking_id' => $booking->id,
        'venue_id' => $venue->id,
        'kind' => Payment::KIND_DP,
        'method' => Payment::METHOD_TRANSFER,
        'amount' => $booking->dp_required,
        'status' => Payment::STATUS_PENDING,
        'proof_path' => 'payment-proofs/dummy.jpg',
        'proof_sha256' => $proofHash !== '' ? $proofHash : hash('sha256', 'proof-'.$booking->ulid),
        'proof_mime' => 'image/jpeg',
        'proof_size' => 1024,
        'submitted_at' => now(),
    ]);

    return $booking->fresh();
}

it('approves payment, generates QR token & reminders, queues notification, and renders QR on customer ticket', function (): void {
    Notification::fake();

    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $booking = createPendingValidationBooking($venue, $court, $customer, 3);

    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/validations/{$booking->ulid}", [
            'decision' => 'approve',
        ])
        ->assertRedirect('/dashboard/validations');

    $booking->refresh();

    expect($booking->status)->toBe(Booking::STATUS_CONFIRMED)
        ->and($booking->paid_total)->toBe($booking->dp_required)
        ->and($booking->qr_token_hash)->not->toBeNull()
        ->and($booking->qr_token_enc)->not->toBeNull()
        ->and($booking->qr_valid_from->equalTo($booking->starts_at->subMinutes(60)))->toBeTrue()
        ->and($booking->qr_valid_until->equalTo($booking->ends_at))->toBeTrue();

    expect(BookingReminder::query()->where('booking_id', $booking->id)->count())->toBe(2);
    expect(AuditLog::query()->where('venue_id', $venue->id)->where('action', 'payment.approved')->exists())->toBeTrue();

    Notification::assertSentTo($customer, BookingConfirmedNotification::class);

    // Customer sees QR SVG data URI on Bookings/Show
    $this->actingAs($customer)
        ->get("/bookings/{$booking->ulid}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Bookings/Show')
            ->where('booking.status', Booking::STATUS_CONFIRMED)
            ->where('booking.qr_svg_data_uri', fn ($uri) => is_string($uri) && str_starts_with($uri, 'data:image/svg+xml;base64,'))
        );
});

it('requests reupload with required reason and extends hold_expires_at', function (): void {
    Notification::fake();

    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $booking = createPendingValidationBooking($venue, $court, $customer, 3);

    // Missing reason fails validation
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/validations/{$booking->ulid}", [
            'decision' => 'reupload',
            'reason' => '',
        ])
        ->assertSessionHasErrors('reason');

    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/validations/{$booking->ulid}", [
            'decision' => 'reupload',
            'reason' => 'Foto bukti transfer buram.',
        ])
        ->assertRedirect('/dashboard/validations');

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_AWAITING_REUPLOAD)
        ->and($booking->status_reason)->toBe('Foto bukti transfer buram.')
        ->and($booking->hold_expires_at)->not->toBeNull();

    Notification::assertSentTo($customer, PaymentActionRequiredNotification::class);
});

it('rejects payment with reason, releases slot immediately, and flags duplicate proof sha256', function (): void {
    Notification::fake();

    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $sharedHash = hash('sha256', 'reused-receipt-bytes');
    $booking1 = createPendingValidationBooking($venue, $court, $customer1, 4, $sharedHash);
    $booking2 = createPendingValidationBooking($venue, $court, $customer2, 5, $sharedHash);

    // Queue shows duplicate SHA-256 warning (Threat T3)
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->get('/dashboard/validations')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard/Validations/Index')
            ->where('bookings', function ($bookings) use ($booking1, $booking2): bool {
                $byUlid = collect($bookings)->keyBy('ulid');

                return ($byUlid[$booking1->ulid]['duplicate_proof_warning'] ?? false) === true
                    && ($byUlid[$booking2->ulid]['duplicate_proof_warning'] ?? false) === true;
            })
        );

    // Reject booking1
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/validations/{$booking1->ulid}", [
            'decision' => 'reject',
            'reason' => 'Bukti transfer duplikat / tidak valid.',
        ])
        ->assertRedirect('/dashboard/validations');

    expect($booking1->fresh()->status)->toBe(Booking::STATUS_REJECTED);

    // Slot is immediately released and can be held by customer2
    $rebooked = app(HoldSlot::class)->execute($customer2, $court, $booking1->starts_at, $booking1->ends_at);
    expect($rebooked->status)->toBe(Booking::STATUS_HELD);
});

it('prevents staff of another venue from validating payment', function (): void {
    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venue2 = Venue::query()->where('slug', 'arena-contoh-dua')->firstOrFail();
    $court1 = Court::withoutGlobalScopes()->where('venue_id', $venue1->id)->firstOrFail();
    $staff2 = User::query()->where('email', 'staff2@example.test')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $booking = createPendingValidationBooking($venue1, $court1, $customer1, 6);

    $this->actingAs($staff2)
        ->withSession(['active_venue_id' => $venue2->id])
        ->post("/dashboard/validations/{$booking->ulid}", [
            'decision' => 'approve',
        ])
        ->assertNotFound();
});
