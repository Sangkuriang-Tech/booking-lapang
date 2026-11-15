<?php

use App\Actions\Booking\GetAvailableSlots;
use App\Actions\Booking\HoldSlot;
use App\Actions\Schedule\CreateScheduleBlock;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('serves venue detail with SportsActivityLocation and BreadcrumbList JSON-LD for active venue', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);

    $response = $this->get('/venue/antic-futsal-center-contoh');

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Venues/Show')
            ->where('venue.slug', 'antic-futsal-center-contoh')
            ->has('venue.courts', 5)
            ->has('slots')
            ->has('json_ld', 2)
            ->where('json_ld.0.@type', 'SportsActivityLocation')
            ->where('json_ld.0.name', 'Antic Futsal Center (contoh)')
            ->where('json_ld.1.@type', 'BreadcrumbList')
        );
});

it('returns 404 for unpublished, grace period, or expired venues', function (): void {
    $this->get('/venue/arena-contoh-dua')->assertNotFound();
    $this->get('/venue/venue-masa-tenggang-contoh')->assertNotFound();
    $this->get('/venue/venue-nonaktif-contoh')->assertNotFound();
});

it('calculates available slots including post-midnight hours, locked bookings, schedule blocks, and multi-hour pricing', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->where('name', 'Mini Soccer A')
        ->firstOrFail();

    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    // Pick the next Saturday (weekend has opens_min=420 [07:00], closes_min=1500 [01:00 next day]).
    $saturday = CarbonImmutable::now('Asia/Jakarta')->next(CarbonImmutable::SATURDAY)->startOfDay();

    // Hold 16:00-18:00 (crosses daytime 300k and nighttime 400k bands -> 700k total).
    $holdStartsAt = $saturday->setTime(16, 0)->utc();
    $holdEndsAt = $saturday->setTime(18, 0)->utc();
    $booking = app(HoldSlot::class)->execute($customer, $court, $holdStartsAt, $holdEndsAt);

    expect($booking->price_total)->toBe(700000);

    // Block 00:00-01:00 next day (start_min = 1440).
    $blockStart = $saturday->addMinutes(1440)->utc();
    $blockEnd = $saturday->addMinutes(1500)->utc();
    app(CreateScheduleBlock::class)->execute($staff, $court, $blockStart, $blockEnd, 'Tutup malam');

    $slots = collect(app(GetAvailableSlots::class)->execute($venue, $court, $saturday));

    // Weekend has 07:00..01:00 = 18 hourly slots (420..1500).
    expect($slots)->toHaveCount(18);

    $slot16 = $slots->firstWhere('start_min', 960);
    $slot17 = $slots->firstWhere('start_min', 1020);
    $slot18 = $slots->firstWhere('start_min', 1080);
    $slot24 = $slots->firstWhere('start_min', 1440);

    expect($slot16['is_available'])->toBeFalse();
    expect($slot16['unavailable_reason'])->toBe('booked');
    expect($slot16['price'])->toBe(300000);
    expect($slot17['is_available'])->toBeFalse();
    expect($slot17['unavailable_reason'])->toBe('booked');
    expect($slot17['price'])->toBe(400000);
    expect($slot18['is_available'])->toBeTrue();
    expect($slot18['price'])->toBe(400000);
    expect($slot24['is_available'])->toBeFalse();
    expect($slot24['unavailable_reason'])->toBe('blocked');
});
