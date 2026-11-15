<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
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

it('creates OTS booking as confirmed or directly checked_in and prevents slot overlap', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();

    $day = CarbonImmutable::now($venue->timezone ?: 'Asia/Jakarta')->addDays(2)->startOfDay();
    $startsAt = $day->setTime(14, 0)->utc();
    $endsAt = $day->setTime(15, 0)->utc();

    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/ots', [
            'court_ulid' => $court->ulid,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'customer_name' => 'Walk In Player',
            'customer_phone' => '081234567899',
            'payment_mode' => Booking::PAYMENT_MODE_FULL,
            'payment_method' => Payment::METHOD_CASH,
            'direct_check_in' => true,
        ])
        ->assertRedirect('/dashboard/check-in');

    $booking = Booking::withoutGlobalScopes()->where('customer_name', 'Walk In Player')->firstOrFail();
    expect($booking->source)->toBe(Booking::SOURCE_OTS)
        ->and($booking->status)->toBe(Booking::STATUS_CHECKED_IN)
        ->and($booking->qr_used_at)->not->toBeNull()
        ->and($booking->paid_total)->toBe($booking->price_total);

    // Overlapping OTS booking on same slot is rejected
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/ots', [
            'court_ulid' => $court->ulid,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'customer_name' => 'Second Player',
            'customer_phone' => '081234567800',
            'payment_mode' => Booking::PAYMENT_MODE_FULL,
            'payment_method' => Payment::METHOD_CASH,
            'direct_check_in' => false,
        ])
        ->assertSessionHasErrors('slot');
});

it('locks OTS booking creation when owner subscription is inactive past H+7', function (): void {
    $expiredVenue = Venue::query()->where('slug', 'venue-nonaktif-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $expiredVenue->id)->firstOrFail();
    $ownerExpired = User::query()->where('email', 'owner_expired@example.test')->firstOrFail();

    $day = CarbonImmutable::now($expiredVenue->timezone ?: 'Asia/Jakarta')->addDays(2)->startOfDay();

    $this->actingAs($ownerExpired)
        ->withSession(['active_venue_id' => $expiredVenue->id])
        ->post('/dashboard/ots', [
            'court_ulid' => $court->ulid,
            'starts_at' => $day->setTime(14, 0)->utc()->toIso8601String(),
            'ends_at' => $day->setTime(15, 0)->utc()->toIso8601String(),
            'customer_name' => 'Walk In Expired',
            'customer_phone' => '081234567899',
            'payment_mode' => Booking::PAYMENT_MODE_FULL,
            'payment_method' => Payment::METHOD_CASH,
        ])
        ->assertForbidden();
});
