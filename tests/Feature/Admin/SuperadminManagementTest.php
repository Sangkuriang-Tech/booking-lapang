<?php

use App\Models\AuditLog;
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

it('allows superadmin to manage plans, subscriptions, accounts, venues with quota check, and view cross-venue transactions', function (): void {
    $superadmin = User::query()->where('email', 'superadmin@example.test')->firstOrFail();
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();

    // Non-superadmin forbidden
    $this->actingAs($owner)->post('/admin/plans', [
        'code' => 'hack',
        'name' => 'Hack',
        'price_monthly' => 0,
        'proof_retention_days' => 30,
        'can_export_reports' => false,
    ])->assertForbidden();

    // Create plan
    $this->actingAs($superadmin)->post('/admin/plans', [
        'code' => 'custom_pilot',
        'name' => 'Custom Pilot',
        'price_monthly' => 250000,
        'max_venues' => 1,
        'max_courts_per_venue' => 5,
        'max_staff_total' => 4,
        'max_payment_accounts_per_venue' => 2,
        'proof_retention_days' => 60,
        'can_export_reports' => true,
        'is_active' => true,
    ])->assertRedirect();

    $plan = SubscriptionPlan::query()->where('code', 'custom_pilot')->firstOrFail();

    // Create new owner account (12-char password)
    $this->actingAs($superadmin)->post('/admin/users', [
        'name' => 'Pemilik Baru',
        'email' => 'pemilikbaru@example.test',
        'password' => 'SuperSecurePass123!',
        'is_active' => true,
    ])->assertRedirect();

    $newOwner = User::query()->where('email', 'pemilikbaru@example.test')->firstOrFail();

    // Assign subscription (snapshot quota)
    $this->actingAs($superadmin)->post('/admin/subscriptions', [
        'owner_ulid' => $newOwner->ulid,
        'plan_id' => $plan->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
    ])->assertRedirect();

    $sub = Subscription::query()->where('owner_id', $newOwner->id)->firstOrFail();
    expect($sub->max_venues)->toBe(1)
        ->and($sub->can_export_reports)->toBeTrue();

    // Create 1st venue succeeds
    $this->actingAs($superadmin)->post('/admin/venues', [
        'owner_ulid' => $newOwner->ulid,
        'name' => 'Arena Pilot Satu',
        'city' => 'Jakarta',
        'address' => 'Jl. Sudirman No. 1',
        'timezone' => 'Asia/Jakarta',
        'is_published' => true,
    ])->assertRedirect()->assertSessionHasNoErrors();

    // Create 2nd venue exceeds max_venues = 1
    $this->actingAs($superadmin)->post('/admin/venues', [
        'owner_ulid' => $newOwner->ulid,
        'name' => 'Arena Pilot Dua',
        'city' => 'Jakarta',
        'address' => 'Jl. Sudirman No. 2',
        'timezone' => 'Asia/Jakarta',
        'is_published' => true,
    ])->assertSessionHasErrors('owner_id');

    expect(Venue::query()->where('owner_id', $newOwner->id)->count())->toBe(1);

    // Cross-venue transactions audit log
    $this->actingAs($superadmin)->get('/admin/transactions')->assertOk();
    expect(AuditLog::query()->where('action', 'admin.cross_venue_transactions_viewed')->exists())->toBeTrue();
});
