<?php

use App\Actions\Court\CreateCourt;
use App\Actions\PaymentAccount\CreatePaymentAccount;
use App\Actions\Staff\AssignVenueUser;
use App\Actions\Staff\RemoveVenueUser;
use App\Actions\Venue\CreateVenue;
use App\Models\Booking;
use App\Models\Sport;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use App\Models\VenueUser;
use App\Support\VenueContext;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('enforces subscription quota limits for venues, courts, staff total, and payment accounts', function (): void {
    $superadmin = User::query()->where('email', 'superadmin@example.test')->firstOrFail();
    $starterPlan = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();
    $sport = Sport::query()->where('slug', 'futsal')->firstOrFail();

    $starterOwner = User::query()->create([
        'role' => User::ROLE_STAFF,
        'name' => 'Starter Owner',
        'email' => 'starter_owner@example.test',
        'password' => Hash::make('PasswordDev123!'),
        'is_active' => true,
    ]);

    Subscription::query()->create([
        'owner_id' => $starterOwner->id,
        'plan_id' => $starterPlan->id,
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addMonth()->toDateString(),
        'is_active' => true,
        'max_venues' => $starterPlan->max_venues,
        'max_courts_per_venue' => $starterPlan->max_courts_per_venue,
        'max_staff_total' => $starterPlan->max_staff_total,
        'max_payment_accounts_per_venue' => $starterPlan->max_payment_accounts_per_venue,
        'proof_retention_days' => $starterPlan->proof_retention_days,
        'can_export_reports' => $starterPlan->can_export_reports,
        'created_by' => $superadmin->id,
    ]);

    $createVenue = app(CreateVenue::class);
    $venue = $createVenue->execute($superadmin, $starterOwner, [
        'name' => 'Starter Venue 1',
        'city' => 'Bandung',
        'address' => 'Jl. Starter 1',
    ]);

    // Starter max_venues = 1 -> second venue must fail
    expect(fn () => $createVenue->execute($superadmin, $starterOwner, [
        'name' => 'Starter Venue 2',
        'city' => 'Bandung',
        'address' => 'Jl. Starter 2',
    ]))->toThrow(ValidationException::class);

    // Starter max_courts_per_venue = 4 -> 5th court must fail
    $createCourt = app(CreateCourt::class);
    foreach (range(1, 4) as $i) {
        $createCourt->execute($starterOwner, $venue, [
            'sport_id' => $sport->id,
            'name' => "Lapangan {$i}",
        ]);
    }
    expect(fn () => $createCourt->execute($starterOwner, $venue, [
        'sport_id' => $sport->id,
        'name' => 'Lapangan 5',
    ]))->toThrow(ValidationException::class);

    // Starter max_payment_accounts_per_venue = 1 -> 2nd account must fail
    $createAccount = app(CreatePaymentAccount::class);
    $firstAccount = $createAccount->execute($starterOwner, $venue, [
        'kind' => VenuePaymentAccount::KIND_BANK,
        'bank_name' => 'BCA',
        'account_number' => '1234567890',
        'account_holder' => 'Starter Owner',
    ]);
    expect($firstAccount->visible_from->lte(now()->addSecond()))->toBeTrue();

    expect(fn () => $createAccount->execute($starterOwner, $venue, [
        'kind' => VenuePaymentAccount::KIND_QRIS,
        'qris_image_path' => 'qris/starter.png',
    ]))->toThrow(ValidationException::class);

    // Starter max_staff_total = 3 -> 4th staff must fail
    $assignStaff = app(AssignVenueUser::class);
    foreach (range(1, 3) as $i) {
        $staff = User::query()->create([
            'role' => User::ROLE_STAFF,
            'name' => "Starter Staff {$i}",
            'email' => "starter_staff_{$i}@example.test",
            'password' => Hash::make('PasswordDev123!'),
            'is_active' => true,
        ]);
        $assignStaff->execute($starterOwner, $venue, $staff, VenueUser::ROLE_STAFF);
    }

    $extraStaff = User::query()->create([
        'role' => User::ROLE_STAFF,
        'name' => 'Starter Staff 4',
        'email' => 'starter_staff_4@example.test',
        'password' => Hash::make('PasswordDev123!'),
        'is_active' => true,
    ]);
    expect(fn () => $assignStaff->execute($starterOwner, $venue, $extraStaff, VenueUser::ROLE_STAFF))
        ->toThrow(ValidationException::class);
});

it('preserves snapshot quota when master subscription plan changes', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $proPlan = SubscriptionPlan::query()->where('code', 'pro')->firstOrFail();

    $proPlan->update(['max_venues' => 1]);

    $subscription = $owner->latestSubscription();
    expect($subscription->max_venues)->toBe(2);
});

it('enforces grace period H+1..H+7 and inactive >H+7 rules accurately', function (): void {
    $customer = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $ownerGrace = User::query()->where('email', 'owner_grace@example.test')->firstOrFail();
    $ownerExpired = User::query()->where('email', 'owner_expired@example.test')->firstOrFail();

    $venueActive = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venueGrace = Venue::query()->where('slug', 'venue-masa-tenggang-contoh')->firstOrFail();
    $venueExpired = Venue::query()->where('slug', 'venue-nonaktif-contoh')->firstOrFail();

    expect($venueActive->subscriptionStatus())->toBe(Subscription::STATUS_ACTIVE);
    expect($venueActive->allowsPublicListing())->toBeTrue();
    expect($customer->can('createOnline', [Booking::class, $venueActive]))->toBeTrue();

    // H+3 grace period: hidden from public & online booking blocked, but OTS allowed
    expect($venueGrace->subscriptionStatus())->toBe(Subscription::STATUS_GRACE_PERIOD);
    expect($venueGrace->allowsPublicListing())->toBeFalse();
    expect($customer->can('createOnline', [Booking::class, $venueGrace]))->toBeFalse();

    $this->actingAs($ownerGrace);
    app(VenueContext::class)->set($venueGrace->id);
    expect($ownerGrace->can('createOts', [Booking::class, $venueGrace]))->toBeTrue();

    // > H+7 expired: both online and OTS blocked
    expect($venueExpired->subscriptionStatus())->toBe(Subscription::STATUS_INACTIVE);
    expect($venueExpired->allowsPublicListing())->toBeFalse();
    expect($customer->can('createOnline', [Booking::class, $venueExpired]))->toBeFalse();

    $this->actingAs($ownerExpired);
    app(VenueContext::class)->set($venueExpired->id);
    expect($ownerExpired->can('createOts', [Booking::class, $venueExpired]))->toBeFalse();
});

it('prevents removing or demoting the last owner of a venue', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $superadmin = User::query()->where('email', 'superadmin@example.test')->firstOrFail();
    $venue = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();

    $membership = VenueUser::query()
        ->where('venue_id', $venue->id)
        ->where('user_id', $owner->id)
        ->firstOrFail();

    expect($superadmin->can('delete', $membership))->toBeFalse();
    expect($superadmin->can('update', $membership))->toBeFalse();

    expect(fn () => app(RemoveVenueUser::class)->execute($superadmin, $venue, $owner))
        ->toThrow(ValidationException::class, 'Pemilik terakhir venue tidak boleh dihapus.');

    expect(fn () => app(AssignVenueUser::class)->execute($superadmin, $venue, $owner, VenueUser::ROLE_STAFF))
        ->toThrow(ValidationException::class, 'Pemilik terakhir venue tidak boleh diturunkan perannya.');
});
