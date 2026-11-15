<?php

use App\Actions\Booking\HoldSlot;
use App\Actions\Schedule\CreateScheduleBlock;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\PriceRule;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('prevents double booking on the same court and overlapping time via exclusion constraint', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $startsAt = CarbonImmutable::now('Asia/Jakarta')->addDay()->setTime(21, 0)->utc();
    $endsAt = $startsAt->addHour();

    $holdSlot = app(HoldSlot::class);

    $first = $holdSlot->execute($customer1, $court, $startsAt, $endsAt, [
        'price_total' => 350000,
        'payment_mode' => Booking::PAYMENT_MODE_DP,
    ]);

    expect($first->status)->toBe(Booking::STATUS_HELD);

    expect(fn () => $holdSlot->execute($customer2, $court, $startsAt, $endsAt, [
        'price_total' => 350000,
        'payment_mode' => Booking::PAYMENT_MODE_DP,
    ]))->toThrow(ValidationException::class, 'Slot baru saja diambil orang lain atau diblokir.');

    // Adjacent non-overlapping slot [22:00, 23:00) succeeds
    $adjacent = $holdSlot->execute($customer2, $court, $endsAt, $endsAt->addHour(), [
        'price_total' => 350000,
        'payment_mode' => Booking::PAYMENT_MODE_DP,
    ]);

    expect($adjacent->status)->toBe(Booking::STATUS_HELD);
});

it('allows booking a slot again once the previous booking is expired or cancelled', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $startsAt = CarbonImmutable::now('Asia/Jakarta')->addDay()->setTime(21, 0)->utc();
    $endsAt = $startsAt->addHour();

    $holdSlot = app(HoldSlot::class);
    $first = $holdSlot->execute($customer1, $court, $startsAt, $endsAt, ['price_total' => 350000]);

    $first->update(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now()]);

    $second = $holdSlot->execute($customer2, $court, $startsAt, $endsAt, ['price_total' => 350000]);
    expect($second->status)->toBe(Booking::STATUS_HELD);
});

it('prevents booking on a blocked schedule slot via trigger and action', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $startsAt = CarbonImmutable::now('Asia/Jakarta')->addDay()->setTime(21, 0)->utc();
    $endsAt = $startsAt->addHour();

    app(CreateScheduleBlock::class)->execute($staff, $court, $startsAt, $endsAt, 'Perawatan rumput');

    expect(fn () => app(HoldSlot::class)->execute($customer, $court, $startsAt, $endsAt, [
        'price_total' => 350000,
    ]))->toThrow(ValidationException::class, 'Slot baru saja diambil orang lain atau diblokir.');
});

it('rejects overlapping price rules on the same court and day type', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->where('name', 'Mini Soccer A')->firstOrFail();

    expect(fn () => PriceRule::query()->create([
        'court_id' => $court->id,
        'day_type' => PriceRule::DAY_TYPE_WEEKDAY,
        'start_min' => 900,
        'end_min' => 1080,
        'price_per_hour' => 300000,
    ]))->toThrow(QueryException::class);
});

it('enforces append-only immutability on booking_status_logs and audit_logs at database level', function (): void {
    $log = BookingStatusLog::query()->firstOrFail();
    $audit = AuditLog::query()->create([
        'action' => 'test.event',
        'created_at' => now(),
    ]);

    expect(fn () => DB::table('booking_status_logs')->where('id', $log->id)->update(['reason' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('booking_status_logs')->where('id', $log->id)->delete())
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_logs')->where('id', $audit->id)->update(['action' => 'tampered']))
        ->toThrow(QueryException::class);

    expect(fn () => DB::table('audit_logs')->where('id', $audit->id)->delete())
        ->toThrow(QueryException::class);
});
