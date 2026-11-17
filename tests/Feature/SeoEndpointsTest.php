<?php

use App\Models\User;
use App\Models\Venue;
use Database\Seeders\LocalDevelopmentSeeder;
use Database\Seeders\SportSeeder;
use Database\Seeders\SubscriptionPlanSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();

    $this->seed([
        SubscriptionPlanSeeder::class,
        SportSeeder::class,
        LocalDevelopmentSeeder::class,
    ]);
});

it('returns dynamic sitemap.xml containing only active and published venues', function (): void {
    $activeOwner = User::query()->where('email', 'owner@example.test')->firstOrFail();

    Venue::query()->create([
        'owner_id' => $activeOwner->id,
        'slug' => 'venue-tersembunyi-draft',
        'name' => 'Venue Tersembunyi Draft',
        'description' => 'Draft',
        'city' => 'Depok',
        'city_slug' => 'depok',
        'address' => 'Jl. Margonda',
        'contact_phone' => '081234560000',
        'timezone' => 'Asia/Jakarta',
        'is_published' => false,
    ]);

    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $body = $response->getContent();
    expect($body)
        ->toContain('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">')
        ->toContain('/venue/antic-futsal-center-contoh</loc>')
        ->not->toContain('arena-contoh-dua')
        ->not->toContain('venue-masa-tenggang-contoh')
        ->not->toContain('venue-tersembunyi-draft');
});

it('returns robots.txt blocking private routes and referencing sitemap', function (): void {
    $response = $this->get('/robots.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $body = $response->getContent();
    expect($body)
        ->toContain('User-agent: *')
        ->toContain('Disallow: /dashboard')
        ->toContain('Disallow: /login')
        ->toContain('Disallow: /account')
        ->toContain('Disallow: /staff/login')
        ->toContain('Sitemap:')
        ->toContain('/sitemap.xml');
});

it('returns llms.txt with site summary and active published venues only', function (): void {
    $response = $this->get('/llms.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');

    $body = $response->getContent();
    expect($body)
        ->toContain('# Booking Lapang')
        ->toContain('Cara Kerja Booking')
        ->toContain('Antic Futsal Center')
        ->not->toContain('Arena Contoh Dua')
        ->not->toContain('Venue Masa Tenggang');
});
