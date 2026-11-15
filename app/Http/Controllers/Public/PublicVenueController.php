<?php

namespace App\Http\Controllers\Public;

use App\Actions\Landing\GetPublicVenues;
use App\Http\Controllers\Controller;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicVenueController extends Controller
{
    public function index(Request $request, GetPublicVenues $getPublicVenues): Response
    {
        $sportFilter = trim((string) $request->query('sport', ''));
        $cityFilter = trim((string) $request->query('city', ''));

        $venues = $getPublicVenues->execute(withDetails: true)
            ->filter(function (Venue $venue) use ($sportFilter, $cityFilter): bool {
                if ($cityFilter !== '' && $venue->city_slug !== $cityFilter) {
                    return false;
                }

                if ($sportFilter !== '') {
                    return $venue->courts->contains(
                        fn ($court): bool => $court->sport?->slug === $sportFilter
                    );
                }

                return true;
            })
            ->map(fn (Venue $venue): array => [
                'ulid' => $venue->ulid,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'city' => $venue->city,
                'address' => $venue->address,
                'courts_count' => $venue->courts->count(),
                'sports' => $venue->courts
                    ->map(fn ($court): ?string => $court->sport?->name)
                    ->filter()
                    ->unique()
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Venues/Index', [
            'venues' => $venues,
            'filters' => [
                'sport' => $sportFilter,
                'city' => $cityFilter,
            ],
        ]);
    }
}
