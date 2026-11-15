<?php

use App\Models\Court;
use App\Models\CourtCategory;
use App\Models\PriceRule;
use App\Models\Sport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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

it('manages court categories, courts with quota enforcement, and price rules with GIST overlap handling', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('owner_id', $owner->id)->firstOrFail();
    $sport = Sport::query()->firstOrFail();

    // Update owner subscription to Starter (max_courts_per_venue = 4)
    $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
    Subscription::query()
        ->where('owner_id', $owner->id)
        ->update([
            'plan_id' => $starter->id,
            'max_courts_per_venue' => 4,
        ]);

    // Create category
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/court-categories', ['name' => 'Indoor Vinyl'])
        ->assertRedirect();

    $category = CourtCategory::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->where('name', 'Indoor Vinyl')
        ->firstOrFail();

    // Fill remaining court quota up to 4
    $existingCount = Court::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->whereNull('deleted_at')
        ->count();

    for ($i = $existingCount + 1; $i <= 4; $i++) {
        $this->actingAs($owner)
            ->withSession(['active_venue_id' => $venue->id])
            ->post('/dashboard/courts', [
                'sport_id' => $sport->id,
                'category_id' => $category->id,
                'name' => "Lapangan Tambahan {$i}",
                'is_active' => true,
            ])
            ->assertRedirect();
    }

    // 5th court must be rejected by quota
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/courts', [
            'sport_id' => $sport->id,
            'category_id' => $category->id,
            'name' => 'Lapangan Melebihi Kuota',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('name');

    $court = Court::withoutGlobalScopes()
        ->where('venue_id', $venue->id)
        ->whereNull('deleted_at')
        ->firstOrFail();

    // Clear existing rules for clean GIST test
    PriceRule::query()->where('court_id', $court->id)->delete();

    // Add price rule 08:00 - 17:00
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/courts/{$court->ulid}/price-rules", [
            'day_type' => PriceRule::DAY_TYPE_WEEKDAY,
            'start_min' => 480,
            'end_min' => 1020,
            'price_per_hour' => 120000,
        ])
        ->assertRedirect();

    // Overlapping rule 16:00 - 20:00 must fail gracefully via GIST 23P01
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/courts/{$court->ulid}/price-rules", [
            'day_type' => PriceRule::DAY_TYPE_WEEKDAY,
            'start_min' => 960,
            'end_min' => 1200,
            'price_per_hour' => 150000,
        ])
        ->assertSessionHasErrors('start_min');

    // Non-overlapping rule 17:00 - 23:00 succeeds
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post("/dashboard/courts/{$court->ulid}/price-rules", [
            'day_type' => PriceRule::DAY_TYPE_WEEKDAY,
            'start_min' => 1020,
            'end_min' => 1380,
            'price_per_hour' => 160000,
        ])
        ->assertRedirect();

    expect(PriceRule::query()->where('court_id', $court->id)->count())->toBe(2);
});
