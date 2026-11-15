<?php

use App\Actions\Booking\CancelBooking;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

function createConfirmedBookingWithToken(
    Venue $venue,
    Court $court,
    User $customer,
    string $rawToken,
    int $priceTotal = 300000,
    int $paidTotal = 300000,
    int $startMinutesOffset = -10,
    int $endMinutesOffset = 50,
): Booking {
    $startsAt = now()->toImmutable()->addMinutes($startMinutesOffset);
    $endsAt = now()->toImmutable()->addMinutes($endMinutesOffset);

    return Booking::withoutGlobalScopes()->create([
        'code' => strtoupper(substr(md5($rawToken), 0, 8)),
        'venue_id' => $venue->id,
        'court_id' => $court->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_phone' => '6281234567890',
        'source' => Booking::SOURCE_ONLINE,
        'status' => Booking::STATUS_CONFIRMED,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'play_date' => $startsAt->setTimezone($venue->timezone ?: 'Asia/Jakarta')->toDateString(),
        'price_total' => $priceTotal,
        'payment_mode' => $paidTotal < $priceTotal ? Booking::PAYMENT_MODE_DP : Booking::PAYMENT_MODE_FULL,
        'dp_required' => $paidTotal,
        'paid_total' => $paidTotal,
        'qr_token_hash' => hash('sha256', $rawToken),
        'qr_token_enc' => $rawToken,
        'qr_valid_from' => $startsAt->subMinutes(60),
        'qr_valid_until' => $endsAt,
        'confirmed_at' => now()->subDay(),
        'created_by' => $customer->id,
    ]);
}

it('enforces QR single-use, venue isolation, time window, and revocation (Threats T7 & T8)', function (): void {
    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venue2 = Venue::query()->where('slug', 'arena-contoh-dua')->firstOrFail();
    $court1 = Court::withoutGlobalScopes()->where('venue_id', $venue1->id)->skip(1)->firstOrFail();
    $staff1 = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $staff2 = User::query()->where('email', 'staff2@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $token = 'valid-token-32bytes-example-0001';
    $booking = createConfirmedBookingWithToken($venue1, $court1, $customer, $token);

    // Scan at wrong venue fails (T7)
    $this->actingAs($staff2)
        ->withSession(['active_venue_id' => $venue2->id])
        ->post('/dashboard/check-in/lookup', ['token' => $token])
        ->assertSessionHasErrors('token');

    app()->forgetScopedInstances();

    // Valid lookup & check-in at venue 1
    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->post('/dashboard/check-in/lookup', ['token' => $token])
        ->assertRedirect('/dashboard/check-in')
        ->assertSessionHasNoErrors();

    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->post('/dashboard/check-in', ['token' => $token])
        ->assertRedirect('/dashboard/check-in');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_CHECKED_IN)
        ->and($booking->fresh()->qr_used_at)->not->toBeNull();

    // Second scan fails (T7 single-use)
    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->post('/dashboard/check-in', ['token' => $token])
        ->assertSessionHasErrors('token');

    // Outside time window (>60 min before start) fails (T8)
    $earlyToken = 'early-token-32bytes-example-0002';
    createConfirmedBookingWithToken($venue1, $court1, $customer, $earlyToken, 300000, 300000, 180, 240);

    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->post('/dashboard/check-in/lookup', ['token' => $earlyToken])
        ->assertSessionHasErrors('token');

    // Revoked / cancelled booking QR fails (T8)
    $court2 = Court::withoutGlobalScopes()->where('venue_id', $venue1->id)->skip(2)->firstOrFail();
    $revokedToken = 'revoked-token-32bytes-example-003';
    $revokedBooking = createConfirmedBookingWithToken($venue1, $court2, $customer, $revokedToken, 300000, 300000, 10, 70);
    app(CancelBooking::class)->execute($staff1, $revokedBooking, 'Batal');

    $this->actingAs($staff1)
        ->withSession(['active_venue_id' => $venue1->id])
        ->post('/dashboard/check-in/lookup', ['token' => $revokedToken])
        ->assertSessionHasErrors('token');
});

it('records on-site settlement for DP booking during check-in and marks booking completed', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->skip(1)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $token = 'dp-token-32bytes-example-0004';
    $booking = createConfirmedBookingWithToken($venue, $court, $customer, $token, 300000, 90000);

    // Cannot check in without settlement
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/check-in', ['token' => $token])
        ->assertSessionHasErrors('settlement_method');

    // Check in with exact remaining settlement (210000)
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/check-in', [
            'token' => $token,
            'settlement_method' => Payment::METHOD_CASH,
            'settlement_amount' => 210000,
        ])
        ->assertRedirect('/dashboard/check-in');

    $booking->refresh();
    expect($booking->status)->toBe(Booking::STATUS_CHECKED_IN)
        ->and($booking->paid_total)->toBe(300000);

    $settlement = Payment::withoutGlobalScopes()
        ->where('booking_id', $booking->id)
        ->where('kind', Payment::KIND_SETTLEMENT)
        ->first();
    expect($settlement)->not->toBeNull()
        ->and($settlement->amount)->toBe(210000)
        ->and($settlement->method)->toBe(Payment::METHOD_CASH);

    // Mark completed
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/check-in/{$booking->ulid}/complete")
        ->assertRedirect('/dashboard/check-in');

    expect($booking->fresh()->status)->toBe(Booking::STATUS_COMPLETED);
});
