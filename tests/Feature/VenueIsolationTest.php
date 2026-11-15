<?php

use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use App\Support\VenueContext;
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

it('isolates venue-scoped queries to the active venue and fails closed when no venue is selected', function (): void {
    $staff = User::query()->where('email', 'staff2@example.test')->firstOrFail();
    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venue2 = Venue::query()->where('slug', 'arena-contoh-dua')->firstOrFail();

    $this->actingAs($staff);

    // Without active venue selected, staff query must fail closed (0 rows)
    expect(Court::query()->count())->toBe(0);
    expect(Booking::query()->count())->toBe(0);

    // Select Venue 1
    app(VenueContext::class)->set($venue1->id);
    expect(Court::query()->pluck('venue_id')->unique()->all())->toBe([$venue1->id]);
    expect(Court::query()->count())->toBe(5);

    // Switch to Venue 2
    app(VenueContext::class)->set($venue2->id);
    expect(Court::query()->pluck('venue_id')->unique()->all())->toBe([$venue2->id]);
    expect(Court::query()->count())->toBe(2);
    expect(Booking::query()->count())->toBe(0);
});

it('denies staff or owner of venue A from accessing or switching to venue C owned by another owner', function (): void {
    $owner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $venue1 = Venue::query()->where('slug', 'antic-futsal-center-contoh')->firstOrFail();
    $venueGrace = Venue::query()->where('slug', 'venue-masa-tenggang-contoh')->firstOrFail();

    $this->actingAs($owner);
    app(VenueContext::class)->set($venue1->id);

    expect($owner->can('view', $venue1))->toBeTrue();
    expect($owner->can('view', $venueGrace))->toBeFalse();
    expect($owner->can('update', $venueGrace))->toBeFalse();

    $courtInVenueGrace = Court::withoutGlobalScopes()->where('venue_id', $venueGrace->id)->firstOrFail();
    expect($owner->can('view', $courtInVenueGrace))->toBeFalse();

    // Attempting to select unauthorized venue via HTTP must be rejected
    $response = $this->post('/dashboard/venues/select', [
        'venue_ulid' => $venueGrace->ulid,
    ]);

    $response->assertForbidden();
});

it('shows venue selection modal only with user venues for multi-venue staff and skips it for single-venue staff', function (): void {
    $multiVenueOwner = User::query()->where('email', 'owner@example.test')->firstOrFail();
    $singleVenueStaff = User::query()->where('email', 'staff1@example.test')->firstOrFail();
    $venueGrace = Venue::query()->where('slug', 'venue-masa-tenggang-contoh')->firstOrFail();

    // Single-venue staff skips modal and goes straight to dashboard
    $this->post('/staff/login', [
        'email' => $singleVenueStaff->email,
        'password' => 'PasswordDev123!',
    ])->assertRedirect(route('dashboard.index'));

    $this->post('/logout');

    // Multi-venue owner is redirected to venue selector
    $this->post('/staff/login', [
        'email' => $multiVenueOwner->email,
        'password' => 'PasswordDev123!',
    ])->assertRedirect(route('dashboard.venues.select'));

    // Selector page only contains owner's venues, never venueGrace
    $response = $this->get('/dashboard/venues/select');
    $response->assertOk();
    $response->assertSee('Antic Futsal Center (contoh)');
    $response->assertSee('Arena Contoh Dua');
    $response->assertDontSee($venueGrace->name);
});

it('prevents customer A from viewing or cancelling customer B booking', function (): void {
    $customer1 = User::query()->where('email', 'customer1@example.test')->firstOrFail();
    $customer2 = User::query()->where('email', 'customer2@example.test')->firstOrFail();

    $bookingOfCustomer1 = Booking::withoutGlobalScopes()
        ->where('customer_id', $customer1->id)
        ->firstOrFail();

    expect($customer1->can('view', $bookingOfCustomer1))->toBeTrue();
    expect($customer2->can('view', $bookingOfCustomer1))->toBeFalse();
    expect($customer2->can('cancel', $bookingOfCustomer1))->toBeFalse();
});
