<?php

use App\Actions\Booking\HoldSlot;
use App\Models\AuditLog;
use App\Models\Court;
use App\Models\ScheduleBlock;
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

it('creates and deletes schedule block and rejects overlap with active booking', function (): void {
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $court = Court::withoutGlobalScopes()->where('venue_id', $venue->id)->firstOrFail();
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();

    $day = CarbonImmutable::now($venue->timezone ?: 'Asia/Jakarta')->addDays(3)->startOfDay();
    $booked = app(HoldSlot::class)->execute(
        $customer,
        $court,
        $day->setTime(19, 0)->utc(),
        $day->setTime(20, 0)->utc(),
    );

    // Block overlapping active booking fails
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/schedule/blocks', [
            'court_ulid' => $court->ulid,
            'starts_at' => $booked->starts_at->toIso8601String(),
            'ends_at' => $booked->ends_at->toIso8601String(),
            'reason' => 'Perawatan',
        ])
        ->assertSessionHasErrors('slot');

    // Non-overlapping block succeeds
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/schedule/blocks', [
            'court_ulid' => $court->ulid,
            'starts_at' => $day->setTime(10, 0)->utc()->toIso8601String(),
            'ends_at' => $day->setTime(12, 0)->utc()->toIso8601String(),
            'reason' => 'Perawatan rutin',
        ])
        ->assertRedirect();

    $block = ScheduleBlock::withoutGlobalScopes()->where('reason', 'Perawatan rutin')->firstOrFail();

    // Delete block
    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->delete("/dashboard/schedule/blocks/{$block->id}")
        ->assertRedirect();

    expect(ScheduleBlock::withoutGlobalScopes()->whereKey($block->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('venue_id', $venue->id)->where('action', 'schedule_block.deleted')->exists())->toBeTrue();
});
