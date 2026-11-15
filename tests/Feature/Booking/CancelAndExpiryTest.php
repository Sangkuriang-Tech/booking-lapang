<?php

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\HoldSlot;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('allows customer to cancel held or eligible confirmed booking and enforces cancel_min_hours', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $targetDay = CarbonImmutable::now('Asia/Jakarta')->addDays(4)->startOfDay();
    $heldBooking = app(HoldSlot::class)->execute($customer2, $court, $targetDay->setTime(19, 0)->utc(), $targetDay->setTime(20, 0)->utc());

    // Customer1 cannot cancel customer2's booking (IDOR -> 403)
    $this->actingAs($customer1)
        ->post("/bookings/{$heldBooking->ulid}/cancel", ['reason' => 'Coba-coba'])
        ->assertForbidden();

    // Customer2 cancels held booking
    $this->actingAs($customer2)
        ->post("/bookings/{$heldBooking->ulid}/cancel", ['reason' => 'Berubah pikiran'])
        ->assertRedirect();

    expect($heldBooking->fresh()->status)->toBe(Booking::STATUS_CANCELLED);

    // Confirmed booking within cancel_min_hours (24h) cannot be cancelled by customer
    $nearBooking = Booking::withoutGlobalScopes()->create([
        'code' => 'NEAR1234',
        'venue_id' => $venue->id,
        'court_id' => $court->id,
        'customer_id' => $customer2->id,
        'customer_name' => $customer2->name,
        'customer_email' => $customer2->email,
        'source' => Booking::SOURCE_ONLINE,
        'status' => Booking::STATUS_CONFIRMED,
        'starts_at' => now()->addHours(6),
        'ends_at' => now()->addHours(7),
        'play_date' => now('Asia/Jakarta')->toDateString(),
        'price_total' => 350000,
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'dp_required' => 350000,
        'paid_total' => 350000,
        'confirmed_at' => now()->subDay(),
        'created_by' => $customer2->id,
    ]);

    expect(fn () => app(CancelBooking::class)->execute($customer2, $nearBooking, 'Mendadak'))
        ->toThrow(ValidationException::class);

    // Confirmed booking > cancel_min_hours (24h) CAN be cancelled and revokes QR token
    $farBooking = Booking::withoutGlobalScopes()->create([
        'code' => 'FAR12345',
        'venue_id' => $venue->id,
        'court_id' => $court->id,
        'customer_id' => $customer2->id,
        'customer_name' => $customer2->name,
        'customer_email' => $customer2->email,
        'source' => Booking::SOURCE_ONLINE,
        'status' => Booking::STATUS_CONFIRMED,
        'starts_at' => now()->addHours(48),
        'ends_at' => now()->addHours(49),
        'play_date' => now('Asia/Jakarta')->addDays(2)->toDateString(),
        'price_total' => 350000,
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'dp_required' => 350000,
        'paid_total' => 350000,
        'qr_token_hash' => hash('sha256', 'dummy-token'),
        'confirmed_at' => now()->subHour(),
        'created_by' => $customer2->id,
    ]);

    $cancelledFar = app(CancelBooking::class)->execute($customer2, $farBooking, 'Jadwal bentrok');

    expect($cancelledFar->status)->toBe(Booking::STATUS_CANCELLED)
        ->and($cancelledFar->qr_revoked_at)->not->toBeNull();
});

it('expires stale held and awaiting_reupload bookings via bookings:expire command and releases slots', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $nextTue = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::TUESDAY);
    $slotAStart = $nextTue->setTime(19, 0)->utc();
    $slotAEnd = $nextTue->setTime(20, 0)->utc();
    $slotBStart = $nextTue->setTime(20, 0)->utc();
    $slotBEnd = $nextTue->setTime(21, 0)->utc();

    $expiredHold = app(HoldSlot::class)->execute($customer1, $court, $slotAStart, $slotAEnd);
    $expiredHold->update(['hold_expires_at' => now()->subMinute()]);

    $expiredReupload = app(HoldSlot::class)->execute($customer2, $court, $slotBStart, $slotBEnd);
    $expiredReupload->update([
        'status' => Booking::STATUS_AWAITING_REUPLOAD,
        'hold_expires_at' => now()->subMinute(),
    ]);

    $this->artisan('bookings:expire')
        ->expectsOutputToContain('Expired: 1, Rejected: 1')
        ->assertSuccessful();

    expect($expiredHold->fresh()->status)->toBe(Booking::STATUS_EXPIRED)
        ->and($expiredReupload->fresh()->status)->toBe(Booking::STATUS_REJECTED);

    // Released slots can now be booked again
    $rebooked = app(HoldSlot::class)->execute($customer2, $court, $slotAStart, $slotAEnd);
    expect($rebooked->status)->toBe(Booking::STATUS_HELD);
});
