<?php

namespace App\Actions\Landing;

use App\Models\Sport;
use App\Models\SubscriptionPlan;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class GetLandingPageData
{
    public const CACHE_KEY = 'landing:page-data:v1';

    public function __construct(
        private readonly GetPublicVenues $getPublicVenues,
    ) {}

    /**
     * Build and cache public landing page data.
     *
     * @return array{
     *     sports: list<array{id: int, slug: string, name: string, courts_count: int}>,
     *     cities: list<array{city: string, city_slug: string}>,
     *     featuredVenues: list<array{
     *         ulid: string,
     *         slug: string,
     *         name: string,
     *         city: string,
     *         city_slug: string,
     *         address: string,
     *         courts_count: int,
     *         sports: list<string>,
     *         min_price_per_hour: int|null,
     *         max_price_per_hour: int|null,
     *         cover_photo: array{url: string, alt_text: string, width: int, height: int}|null
     *     }>,
     *     plans: list<array{
     *         code: string,
     *         name: string,
     *         price_monthly: int,
     *         max_venues: int|null,
     *         max_courts_per_venue: int|null,
     *         max_staff_total: int|null,
     *         max_payment_accounts_per_venue: int|null,
     *         proof_retention_days: int,
     *         can_export_reports: bool
     *     }>,
     *     stats: array{venues_count: int, courts_count: int, sports_count: int}
     * }
     */
    public function execute(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            $publicVenues = $this->getPublicVenues->execute(withDetails: true);

            $courtCountsBySport = [];
            $totalCourtsCount = 0;

            foreach ($publicVenues as $venue) {
                foreach ($venue->courts as $court) {
                    $totalCourtsCount++;
                    $courtCountsBySport[$court->sport_id] = ($courtCountsBySport[$court->sport_id] ?? 0) + 1;
                }
            }

            $sports = Sport::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'slug', 'name'])
                ->map(fn (Sport $sport): array => [
                    'id' => $sport->id,
                    'slug' => $sport->slug,
                    'name' => $sport->name,
                    'courts_count' => $courtCountsBySport[$sport->id] ?? 0,
                ])
                ->values()
                ->all();

            $cities = $publicVenues
                ->map(fn (Venue $venue): array => [
                    'city' => $venue->city,
                    'city_slug' => $venue->city_slug,
                ])
                ->unique('city_slug')
                ->sortBy('city')
                ->values()
                ->all();

            $featuredVenues = $publicVenues
                ->take(6)
                ->map(function (Venue $venue): array {
                    $prices = $venue->courts
                        ->flatMap(fn ($court) => $court->priceRules->pluck('price_per_hour'))
                        ->filter(fn ($price): bool => is_int($price) && $price > 0)
                        ->values();

                    $cover = $venue->photos->first();
                    $coverPhoto = null;
                    if ($cover !== null) {
                        $coverPhoto = [
                            'url' => str_starts_with($cover->path, 'http') || str_starts_with($cover->path, '/')
                                ? $cover->path
                                : Storage::disk('public')->url($cover->path),
                            'alt_text' => $cover->alt_text ?: 'Foto lapangan '.$venue->name,
                            'width' => $cover->width ?: 800,
                            'height' => $cover->height ?: 500,
                        ];
                    }

                    $sportNames = $venue->courts
                        ->map(fn ($court): ?string => $court->sport?->name)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all();

                    return [
                        'ulid' => $venue->ulid,
                        'slug' => $venue->slug,
                        'name' => $venue->name,
                        'city' => $venue->city,
                        'city_slug' => $venue->city_slug,
                        'address' => $venue->address,
                        'courts_count' => $venue->courts->count(),
                        'sports' => $sportNames,
                        'min_price_per_hour' => $prices->isNotEmpty() ? (int) $prices->min() : null,
                        'max_price_per_hour' => $prices->isNotEmpty() ? (int) $prices->max() : null,
                        'cover_photo' => $coverPhoto,
                    ];
                })
                ->values()
                ->all();

            $plans = SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('price_monthly')
                ->get()
                ->map(fn (SubscriptionPlan $plan): array => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'price_monthly' => $plan->price_monthly,
                    'max_venues' => $plan->max_venues,
                    'max_courts_per_venue' => $plan->max_courts_per_venue,
                    'max_staff_total' => $plan->max_staff_total,
                    'max_payment_accounts_per_venue' => $plan->max_payment_accounts_per_venue,
                    'proof_retention_days' => $plan->proof_retention_days,
                    'can_export_reports' => $plan->can_export_reports,
                ])
                ->values()
                ->all();

            return [
                'sports' => $sports,
                'cities' => $cities,
                'featuredVenues' => $featuredVenues,
                'plans' => $plans,
                'stats' => [
                    'venues_count' => $publicVenues->count(),
                    'courts_count' => $totalCourtsCount,
                    'sports_count' => count($sports),
                ],
            ];
        });
    }
}
