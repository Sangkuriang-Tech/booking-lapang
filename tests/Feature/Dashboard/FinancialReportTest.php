<?php

use App\Actions\Report\GetVenueFinancialReport;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('calculates financial report per active venue, detects T11 discrepancies, and gates CSV export by subscription', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('owner_id', $owner->id)->firstOrFail();
    $otherVenue = Venue::query()->where('owner_id', '!=', $owner->id)->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $otherCourt = Court::withoutGlobalScopes()->where('venue_id', $otherVenue->id)->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $playDate = CarbonImmutable::now($venue->timezone ?: 'Asia/Jakarta')->addDays(5)->startOfDay();
    $playDateString = $playDate->toDateString();

    // Clear any seeded bookings on $playDateString
    Booking::withoutGlobalScopes()->where('play_date', $playDateString)->delete();

    // 1) Confirmed booking WITH approved payment on active venue
    $paidBooking = Booking::query()->create([
        'venue_id' => $venue->id,
        'court_id' => $court->id,
        'customer_id' => $customer->id,
        'code' => Str::upper(Str::random(8)),
        'source' => Booking::SOURCE_ONLINE,
        'customer_name' => 'Pembooking Sah',
        'customer_phone' => '628123456789',
        'play_date' => $playDateString,
        'starts_at' => $playDate->setTime(10, 0)->utc(),
        'ends_at' => $playDate->setTime(11, 0)->utc(),
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'price_total' => 150000,
        'dp_required' => 0,
        'paid_total' => 150000,
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    Payment::query()->create([
        'booking_id' => $paidBooking->id,
        'venue_id' => $venue->id,
        'kind' => Payment::KIND_FULL,
        'method' => Payment::METHOD_TRANSFER,
        'amount' => 150000,
        'status' => Payment::STATUS_APPROVED,
        'reviewed_by' => $owner->id,
        'reviewed_at' => now(),
    ]);

    // 2) Confirmed booking WITHOUT approved payment (Threat T11 discrepancy)
    Booking::query()->create([
        'venue_id' => $venue->id,
        'court_id' => $court->id,
        'customer_id' => $customer->id,
        'code' => Str::upper(Str::random(8)),
        'source' => Booking::SOURCE_OTS,
        'customer_name' => 'Pembooking Selisih',
        'customer_phone' => '628123456780',
        'play_date' => $playDateString,
        'starts_at' => $playDate->setTime(12, 0)->utc(),
        'ends_at' => $playDate->setTime(13, 0)->utc(),
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'price_total' => 200000,
        'dp_required' => 0,
        'paid_total' => 0,
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    // 3) Booking on OTHER venue (must NOT leak into active venue report)
    $otherBooking = Booking::query()->create([
        'venue_id' => $otherVenue->id,
        'court_id' => $otherCourt->id,
        'customer_id' => $customer->id,
        'code' => Str::upper(Str::random(8)),
        'source' => Booking::SOURCE_ONLINE,
        'customer_name' => 'Pembooking Venue Lain',
        'customer_phone' => '628123456781',
        'play_date' => $playDateString,
        'starts_at' => $playDate->setTime(14, 0)->utc(),
        'ends_at' => $playDate->setTime(15, 0)->utc(),
        'payment_mode' => Booking::PAYMENT_MODE_FULL,
        'price_total' => 999000,
        'dp_required' => 0,
        'paid_total' => 999000,
        'status' => Booking::STATUS_CONFIRMED,
    ]);

    Payment::query()->create([
        'booking_id' => $otherBooking->id,
        'venue_id' => $otherVenue->id,
        'kind' => Payment::KIND_FULL,
        'method' => Payment::METHOD_TRANSFER,
        'amount' => 999000,
        'status' => Payment::STATUS_APPROVED,
    ]);

    $report = app(GetVenueFinancialReport::class)->execute($venue, 'daily', $playDateString);

    expect($report['summary']['total_revenue'])->toBe(150000)
        ->and($report['summary']['total_bookings'])->toBe(2)
        ->and($report['summary']['discrepancy_count'])->toBe(1)
        ->and($report['discrepancies'][0]['customer_name'])->toBe('Pembooking Selisih');

    // CSV export gated by can_export_reports
    Subscription::query()->where('owner_id', $owner->id)->update(['can_export_reports' => false]);
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->get("/dashboard/reports/export?period=daily&date={$playDateString}")
        ->assertForbidden();

    Subscription::query()->where('owner_id', $owner->id)->update(['can_export_reports' => true]);
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->get("/dashboard/reports/export?period=daily&date={$playDateString}")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
});
