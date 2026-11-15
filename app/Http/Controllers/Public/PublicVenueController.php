<?php

namespace App\Http\Controllers\Public;

use App\Actions\Booking\GetAvailableSlots;
use App\Actions\Booking\GetVenueDetail;
use App\Actions\Landing\GetPublicVenues;
use App\Http\Controllers\Controller;
use App\Models\Court;
use App\Models\Venue;
use App\Models\VenuePhoto;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PublicVenueController extends Controller
{
    public function index(Request $request, GetPublicVenues $getPublicVenues): Response
    {
        $sportFilter = trim((string) $request->query('sport', ''));
        $cityFilter = trim((string) $request->query('city', ''));
        $searchQuery = mb_strtolower(trim((string) $request->query('q', '')));

        $venues = $getPublicVenues->execute(withDetails: true)
            ->filter(function (Venue $venue) use ($sportFilter, $cityFilter, $searchQuery): bool {
                if ($cityFilter !== '' && $venue->city_slug !== $cityFilter) {
                    return false;
                }

                if ($sportFilter !== '' && ! $venue->courts->contains(
                    fn ($court): bool => $court->sport?->slug === $sportFilter
                )) {
                    return false;
                }

                if ($searchQuery !== '') {
                    $haystack = mb_strtolower($venue->name.' '.$venue->city.' '.$venue->address);

                    return str_contains($haystack, $searchQuery);
                }

                return true;
            })
            ->map(function (Venue $venue): array {
                $minPrice = $venue->courts
                    ->flatMap(fn (Court $court) => $court->priceRules)
                    ->min('price_per_hour');

                $coverPhoto = $venue->photos->first();

                return [
                    'ulid' => $venue->ulid,
                    'slug' => $venue->slug,
                    'name' => $venue->name,
                    'city' => $venue->city,
                    'address' => $venue->address,
                    'courts_count' => $venue->courts->count(),
                    'min_price_per_hour' => $minPrice !== null ? (int) $minPrice : null,
                    'cover_photo_url' => $coverPhoto !== null ? Storage::disk('public')->url($coverPhoto->path) : null,
                    'sports' => $venue->courts
                        ->map(fn ($court): ?string => $court->sport?->name)
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Venues/Index', [
            'venues' => $venues,
            'filters' => [
                'sport' => $sportFilter,
                'city' => $cityFilter,
                'q' => $searchQuery,
            ],
        ]);
    }

    public function show(
        Request $request,
        string $slug,
        GetVenueDetail $getVenueDetail,
        GetAvailableSlots $getAvailableSlots,
    ): Response {
        $detail = $getVenueDetail->execute($slug);
        $venue = $detail['venue'];
        $timezone = $venue->timezone ?: 'Asia/Jakarta';

        $dateParam = trim((string) $request->query('date', ''));
        try {
            $playDate = $dateParam !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $dateParam, $timezone)->startOfDay()
                : CarbonImmutable::now($timezone)->startOfDay();
        } catch (Throwable) {
            $playDate = CarbonImmutable::now($timezone)->startOfDay();
        }

        $today = CarbonImmutable::now($timezone)->startOfDay();
        if ($playDate->lessThan($today)) {
            $playDate = $today;
        }

        $courtUlid = trim((string) $request->query('court', ''));
        /** @var Court|null $selectedCourt */
        $selectedCourt = $courtUlid !== ''
            ? $venue->courts->firstWhere('ulid', $courtUlid)
            : $venue->courts->first();

        if ($selectedCourt === null) {
            $selectedCourt = $venue->courts->first();
        }

        $slots = $selectedCourt !== null
            ? $getAvailableSlots->execute($venue, $selectedCourt, $playDate)
            : [];

        return Inertia::render('Venues/Show', [
            'venue' => [
                'ulid' => $venue->ulid,
                'slug' => $venue->slug,
                'name' => $venue->name,
                'description' => $venue->description,
                'city' => $venue->city,
                'address' => $venue->address,
                'contact_phone' => $venue->contact_phone,
                'timezone' => $timezone,
                'hold_minutes' => $venue->hold_minutes,
                'dp_percent' => $venue->dp_percent,
                'cancel_min_hours' => $venue->cancel_min_hours,
                'photos' => $venue->photos->map(fn (VenuePhoto $photo): array => [
                    'url' => Storage::disk('public')->url($photo->path),
                    'alt_text' => $photo->alt_text,
                    'width' => $photo->width,
                    'height' => $photo->height,
                    'is_cover' => $photo->is_cover,
                ])->values()->all(),
                'operating_hours' => $venue->operatingHours->map(fn ($hour): array => [
                    'weekday' => $hour->weekday,
                    'opens_min' => $hour->opens_min,
                    'closes_min' => $hour->closes_min,
                    'is_closed' => $hour->is_closed,
                ])->values()->all(),
                'courts' => $venue->courts->map(fn (Court $court): array => [
                    'ulid' => $court->ulid,
                    'name' => $court->name,
                    'description' => $court->description,
                    'sport' => $court->sport?->name,
                    'category' => $court->category?->name,
                    'min_price' => $court->priceRules->min('price_per_hour'),
                ])->values()->all(),
            ],
            'selected_court_ulid' => $selectedCourt?->ulid,
            'selected_date' => $playDate->toDateString(),
            'slots' => $slots,
            'json_ld' => $detail['json_ld'],
        ]);
    }
}
