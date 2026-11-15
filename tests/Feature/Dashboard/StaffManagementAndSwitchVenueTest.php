<?php

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('enforces max_staff_total quota, protects last owner, and validates venue switching', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue = Venue::query()->where('owner_id', $owner->id)->firstOrFail();
    $otherVenue = Venue::query()->where('owner_id', '!=', $owner->id)->firstOrFail();

    // Protect last owner from removal (Policy blocks with 403)
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->delete("/dashboard/staff/{$owner->ulid}")
        ->assertForbidden();

    // Limit max_staff_total to 1 for test
    $starter = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
    Subscription::query()
        ->where('owner_id', $owner->id)
        ->update([
            'plan_id' => $starter->id,
            'max_staff_total' => 1,
        ]);

    $newStaff = User::query()->create([
        'role' => User::ROLE_STAFF,
        'name' => 'Extra Staff',
        'email' => 'extrastaff@example.test',
        'password' => Hash::make('StrongPassword123!'),
        'is_active' => true,
    ]);

    // Already has staff1 in seeded data -> adding extra staff exceeds max_staff_total = 1
    $this->actingAs($owner)
        ->withSession(['active_venue_id' => $venue->id])
        ->post('/dashboard/staff', [
            'email' => $newStaff->email,
            'role' => VenueUser::ROLE_STAFF,
        ])
        ->assertSessionHasErrors('user_id');

    // Switching to another owner's venue must fail (403)
    $this->actingAs($owner)
        ->post('/dashboard/venues/select', [
            'venue_ulid' => $otherVenue->ulid,
        ])
        ->assertForbidden();
});
