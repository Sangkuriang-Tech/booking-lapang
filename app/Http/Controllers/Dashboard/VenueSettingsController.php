<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Schedule\UpdateOperatingHours;
use App\Actions\Venue\UpdateVenueSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateOperatingHoursRequest;
use App\Http\Requests\Dashboard\UpdateVenueSettingsRequest;
use App\Models\OperatingHour;
use App\Models\User;
use App\Models\VenuePhoto;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VenueSettingsController extends Controller
{
    public function edit(Request $request, VenueContext $venueContext): Response
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        $hours = OperatingHour::query()
            ->where('venue_id', $venue->id)
            ->orderBy('weekday')
            ->get(['weekday', 'opens_min', 'closes_min', 'is_closed'])
            ->keyBy('weekday');

        $normalizedHours = collect(range(0, 6))->map(function (int $day) use ($hours): array {
            $row = $hours->get($day);

            return [
                'weekday' => $day,
                'opens_min' => $row?->opens_min ?? 420,
                'closes_min' => $row?->closes_min ?? 1380,
                'is_closed' => $row?->is_closed ?? false,
            ];
        })->values()->all();

        $photos = VenuePhoto::query()
            ->with(['court' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'ulid', 'name')])
            ->where('venue_id', $venue->id)
            ->orderByDesc('is_cover')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (VenuePhoto $photo): array => [
                'id' => $photo->id,
                'court_id' => $photo->court_id,
                'court_name' => $photo->court?->name,
                'url' => '/storage/'.$photo->path,
                'alt_text' => $photo->alt_text,
                'width' => $photo->width,
                'height' => $photo->height,
                'is_cover' => $photo->is_cover,
            ])
            ->values()
            ->all();

        $courts = $venue->courts()
            ->whereNull('deleted_at')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'ulid', 'name']);

        return Inertia::render('Dashboard/Settings/Venue', [
            'venue' => [
                'ulid' => $venue->ulid,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'description' => $venue->description,
                'city' => $venue->city,
                'address' => $venue->address,
                'contact_phone' => $venue->contact_phone,
                'timezone' => $venue->timezone,
                'hold_minutes' => $venue->hold_minutes,
                'dp_percent' => $venue->dp_percent,
                'cancel_min_hours' => $venue->cancel_min_hours,
                'reupload_minutes' => $venue->reupload_minutes,
                'is_published' => $venue->is_published,
            ],
            'operatingHours' => $normalizedHours,
            'photos' => $photos,
            'courts' => $courts,
        ]);
    }

    public function update(
        UpdateVenueSettingsRequest $request,
        VenueContext $venueContext,
        UpdateVenueSettings $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $request->validated());

        return back()->with('success', 'Pengaturan venue berhasil diperbarui.');
    }

    public function updateHours(
        UpdateOperatingHoursRequest $request,
        VenueContext $venueContext,
        UpdateOperatingHours $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $request->validated('hours'));

        return back()->with('success', 'Jam operasional venue berhasil diperbarui.');
    }
}
