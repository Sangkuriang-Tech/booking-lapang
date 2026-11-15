<?php

namespace App\Http\Middleware;

use App\Support\VenueContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $venueContext = app(VenueContext::class);
        $activeVenue = $venueContext->venue();

        $availableVenues = [];
        if ($user !== null && $user->isStaff()) {
            $availableVenues = $user->venues()
                ->whereNull('venues.deleted_at')
                ->orderBy('venues.name')
                ->get(['venues.ulid', 'venues.name', 'venues.city', 'venues.timezone'])
                ->map(fn ($venue): array => [
                    'ulid' => $venue->ulid,
                    'name' => $venue->name,
                    'city' => $venue->city,
                    'timezone' => $venue->timezone,
                    'role' => $venue->pivot->role,
                ])
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user !== null ? [
                    'ulid' => $user->ulid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
            ],
            'activeVenue' => $activeVenue !== null ? [
                'ulid' => $activeVenue->ulid,
                'name' => $activeVenue->name,
                'city' => $activeVenue->city,
                'timezone' => $activeVenue->timezone,
                'subscription_status' => $activeVenue->subscriptionStatus(),
            ] : null,
            'availableVenues' => $availableVenues,
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
                'error' => fn (): ?string => $request->session()->get('error'),
            ],
        ];
    }
}
