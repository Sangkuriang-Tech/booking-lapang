<?php

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
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

it('transitions ended checked_in bookings to completed and confirmed bookings to no_show', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $courts = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->take(2)->get();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $endedCheckedIn = Booking::withoutGlobalScopes()->create([
        'code' => 'ENDCHK01',
        'venue_id' => $venue->id,
        'court_id' => $courts[0]->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'source' => Booking::SOURCE_ONLINE,
        'status' => Booking::STATUS_CHECKED_IN,
        'starts_at' => now()->toImmutable()->subDays(2)->setTime(10, 0),
        'ends_at' => now()->toImmutable()->subDays(2)->setTime(11, 0),
        'play_date' => now('Asia/Jakarta')->subDays(2)->toDateString(),
        'price_total' => 200000,
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'dp_required' => 200000,
        'paid_total' => 200000,
        'checked_in_at' => now()->subHours(2),
        'created_by' => $customer->id,
    ]);

    $endedConfirmed = Booking::withoutGlobalScopes()->create([
        'code' => 'ENDCNF02',
        'venue_id' => $venue->id,
        'court_id' => $courts[1]->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'source' => Booking::SOURCE_ONLINE,
        'status' => Booking::STATUS_CONFIRMED,
        'starts_at' => now()->toImmutable()->subDays(2)->setTime(12, 0),
        'ends_at' => now()->toImmutable()->subDays(2)->setTime(13, 0),
        'play_date' => now('Asia/Jakarta')->subDays(2)->toDateString(),
        'price_total' => 200000,
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'dp_required' => 200000,
        'paid_total' => 200000,
        'confirmed_at' => now()->subDay(),
        'created_by' => $customer->id,
    ]);

    $this->artisan('bookings:transition-ended')->assertSuccessful();

    expect($endedCheckedIn->fresh()->status)->toBe(Booking::STATUS_COMPLETED)
        ->and($endedCheckedIn->fresh()->completed_at)->not->toBeNull()
        ->and($endedConfirmed->fresh()->status)->toBe(Booking::STATUS_NO_SHOW);

    expect(BookingStatusLog::query()
        ->where('booking_id', $endedConfirmed->id)
        ->where('to_status', Booking::STATUS_NO_SHOW)
        ->where('actor_kind', 'system')
        ->exists())->toBeTrue();
});
