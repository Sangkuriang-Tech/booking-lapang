<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Auth\SelectActiveVenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\SelectVenueRequest;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueSelectionController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $venues = $user->isSuperadmin()
            ? Venue::query()->orderBy('name')->get(['ulid', 'name', 'city', 'timezone'])
                ->map(fn (Venue $venue): array => [
                    'ulid' => $venue->ulid,
                    'name' => $venue->name,
                    'city' => $venue->city,
                    'timezone' => $venue->timezone,
                    'role' => 'superadmin',
                ])
            : $user->venues()
                ->whereNull('venues.deleted_at')
                ->orderBy('venues.name')
                ->get(['venues.ulid', 'venues.name', 'venues.city', 'venues.timezone'])
                ->map(fn (Venue $venue): array => [
                    'ulid' => $venue->ulid,
                    'name' => $venue->name,
                    'city' => $venue->city,
                    'timezone' => $venue->timezone,
                    'role' => $venue->pivot->role,
                ]);

        return Inertia::render('Dashboard/SelectVenue', [
            'venues' => $venues->values()->all(),
        ]);
    }

    public function store(SelectVenueRequest $request, SelectActiveVenue $action): RedirectResponse
    {
        $venue = Venue::query()
            ->where('ulid', $request->string('venue_ulid')->toString())
            ->firstOrFail();

        $action->execute($request->user(), $venue->id);

        return redirect()->route('dashboard.index');
    }
}
