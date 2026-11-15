<?php

namespace App\Actions\Booking;

use App\Models\OperatingHour;
use App\Models\Scopes\VenueScope;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

class GetVenueDetail
{
    /**
     * @return array{
     *     venue: Venue,
     *     json_ld: array<int, array<string, mixed>>
     * }
     */
    public function execute(string $slug): array
    {
        $venue = Venue::query()
            ->where('slug', $slug)
            ->with([
                'photos' => static function ($query): void {
                    $query->withoutGlobalScope(VenueScope::class)
                        ->orderByDesc('is_cover')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
                'operatingHours' => static function ($query): void {
                    $query->withoutGlobalScope(VenueScope::class)
                        ->orderBy('weekday');
                },
                'courts' => static function ($query): void {
                    $query->withoutGlobalScope(VenueScope::class)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->with([
                            'sport:id,slug,name',
                            'category:id,name',
                            'priceRules' => static function ($priceQuery): void {
                                $priceQuery->orderBy('day_type')->orderBy('start_min');
                            },
                        ]);
                },
            ])
            ->first();

        if ($venue === null || ! $venue->allowsPublicListing()) {
            throw (new ModelNotFoundException)->setModel(Venue::class, [$slug]);
        }

        return [
            'venue' => $venue,
            'json_ld' => $this->buildJsonLd($venue),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildJsonLd(Venue $venue): array
    {
        $venueUrl = url('/venue/'.$venue->slug);
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $openingHours = $venue->operatingHours
            ->filter(fn (OperatingHour $hour): bool => ! $hour->is_closed)
            ->map(function (OperatingHour $hour) use ($dayNames): array {
                $opensHour = intdiv($hour->opens_min, 60);
                $opensMin = $hour->opens_min % 60;
                $closesHour = intdiv($hour->closes_min, 60) % 24;
                $closesMin = $hour->closes_min % 60;

                return [
                    '@type' => 'OpeningHoursSpecification',
                    'dayOfWeek' => $dayNames[$hour->weekday] ?? 'Monday',
                    'opens' => sprintf('%02d:%02d', $opensHour, $opensMin),
                    'closes' => sprintf('%02d:%02d', $closesHour, $closesMin),
                ];
            })
            ->values()
            ->all();

        $images = $venue->photos
            ->map(fn (VenuePhoto $photo): string => url(Storage::disk('public')->url($photo->path)))
            ->values()
            ->all();

        $sportsActivityLocation = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'SportsActivityLocation',
            'name' => $venue->name,
            'description' => $venue->description,
            'url' => $venueUrl,
            'telephone' => $venue->contact_phone,
            'image' => $images !== [] ? $images : null,
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $venue->address,
                'addressLocality' => $venue->city,
                'addressCountry' => 'ID',
            ],
            'geo' => ($venue->latitude !== null && $venue->longitude !== null) ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $venue->latitude,
                'longitude' => (float) $venue->longitude,
            ] : null,
            'openingHoursSpecification' => $openingHours !== [] ? $openingHours : null,
        ], fn (mixed $value): bool => $value !== null);

        $breadcrumbList = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Beranda',
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Cari Venue',
                    'item' => url('/venues'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $venue->name,
                    'item' => $venueUrl,
                ],
            ],
        ];

        return [$sportsActivityLocation, $breadcrumbList];
    }
}
