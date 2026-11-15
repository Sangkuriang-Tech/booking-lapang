<?php

use App\Models\AuditLog;
use App\Models\OperatingHour;
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

it('allows venue owner to update venue settings and operating hours including closes_min > 1440', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('owner_id', $owner->id)->firstOrFail();

    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->put('/dashboard/settings/venue', [
            'name' => 'Antic Futsal Updated',
            'description' => 'Lapangan futsal berstandar nasional.',
            'city' => 'Bandung',
            'address' => 'Jl. Merdeka No. 99',
            'contact_phone' => '081234567890',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 25,
            'dp_percent' => 40,
            'cancel_min_hours' => 12,
            'reupload_minutes' => 90,
            'is_published' => true,
        ])
        ->assertRedirect();

    $venue->refresh();
    expect($venue->name)->toBe('Antic Futsal Updated')
        ->and($venue->hold_minutes)->toBe(25)
        ->and($venue->dp_percent)->toBe(40)
        ->and($venue->cancel_min_hours)->toBe(12)
        ->and($venue->reupload_minutes)->toBe(90);

    $hours = collect(range(0, 6))->map(fn (int $day): array => [
        'weekday' => $day,
        'opens_min' => 480,
        'closes_min' => $day === 6 ? 1560 : 1380, // Sabtu tutup 02.00 (+1 hari)
        'is_closed' => $day === 0,
    ])->all();

    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->put('/dashboard/settings/operating-hours', ['hours' => $hours])
        ->assertRedirect();

    $saturday = OperatingHour::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->where('weekday', 6)
        ->firstOrFail();

    expect($saturday->closes_min)->toBe(1560);
    expect(AuditLog::query()->where('venue_id', $venue->id)->where('action', 'operating_hours.updated')->exists())->toBeTrue();
});

it('forbids venue staff (non-owner) from updating venue settings', function (): void {
    $staff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();

    $this->actingAs($staff)
        ->withSession(['active_venue_id' => $venue->id])
        ->put('/dashboard/settings/venue', [
            'name' => 'Unauthorized Change',
            'city' => 'Bandung',
            'address' => 'Jl. Merdeka',
            'timezone' => 'Asia/Jakarta',
            'hold_minutes' => 20,
            'dp_percent' => 30,
            'cancel_min_hours' => 24,
            'reupload_minutes' => 60,
            'is_published' => true,
        ])
        ->assertForbidden();
});
