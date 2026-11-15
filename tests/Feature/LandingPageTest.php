<?php

use App\Actions\Landing\GetLandingPageData;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Cache::flush();

    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('renders the landing page with only active and published venues and caches the payload', function (): void {
    // Create an unpublished venue for an active owner
    $activeOwner = User::query()->where('email', 'owner@example.test')->firstOrFail();

    Venue::query()->create([
        'owner_id' => $activeOwner->id,
        'slug' => 'venue-draft-belum-terbit',
        'name' => 'Venue Draft Belum Terbit',
        'description' => 'Belum dipublikasikan',
        'city' => 'Bandung',
        'city_slug' => 'bandung',
        'address' => 'Jl. Draft No. 1',
        'contact_phone' => '081234567890',
        'timezone' => 'Asia/Jakarta',
        'is_published' => false,
    ]);

    // Create an inactive subscription owner and published venue
    $inactiveOwner = User::query()->create([
        'name' => 'Owner Nonaktif',
        'email' => 'owner-inactive@example.test',
        'password' => 'PasswordDev123!',
        'role' => User::ROLE_STAFF,
    ]);

    $starterPlan = SubscriptionPlan::query()->where('code', 'starter')->firstOrFail();

    Subscription::query()->create([
        'owner_id' => $inactiveOwner->id,
        'plan_id' => $starterPlan->id,
        'starts_on' => now('Asia/Jakarta')->subDays(40)->toDateString(),
        'ends_on' => now('Asia/Jakarta')->subDays(15)->toDateString(),
        'is_active' => true,
        'max_venues' => $starterPlan->max_venues,
        'max_courts_per_venue' => $starterPlan->max_courts_per_venue,
        'max_staff_total' => $starterPlan->max_staff_total,
        'max_payment_accounts_per_venue' => $starterPlan->max_payment_accounts_per_venue,
        'proof_retention_days' => $starterPlan->proof_retention_days,
        'can_export_reports' => $starterPlan->can_export_reports,
    ]);

    Venue::query()->create([
        'owner_id' => $inactiveOwner->id,
        'slug' => 'venue-langganan-habis',
        'name' => 'Venue Langganan Habis',
        'description' => 'Langganan sudah kedaluwarsa',
        'city' => 'Surabaya',
        'city_slug' => 'surabaya',
        'address' => 'Jl. Expired No. 99',
        'contact_phone' => '081299998888',
        'timezone' => 'Asia/Jakarta',
        'is_published' => true,
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertDontSee('accounts.google.com/gsi/client', false);

    $response->assertInertia(fn (Assert $page) => $page
        ->component('Home')
        ->has('sports')
        ->has('plans', 3)
        ->where('stats.venues_count', 1)
        ->has('featuredVenues', 1)
        ->where('featuredVenues', function ($venues): bool {
            $slugs = collect($venues)->pluck('slug')->all();

            return in_array('antic-futsal-center-contoh', $slugs, true)
                && ! in_array('arena-contoh-dua', $slugs, true)
                && ! in_array('venue-masa-tenggang-contoh', $slugs, true)
                && ! in_array('venue-draft-belum-terbit', $slugs, true)
                && ! in_array('venue-langganan-habis', $slugs, true);
        })
    );

    expect(Cache::has(GetLandingPageData::CACHE_KEY))->toBeTrue();
});

it('serves the public venues directory route without 404 for hero search queries', function (): void {
    $response = $this->get('/venues?sport=futsal&city=jakarta-selatan');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Venues/Index')
        ->where('filters.sport', 'futsal')
        ->where('filters.city', 'jakarta-selatan')
        ->has('venues')
    );
});
