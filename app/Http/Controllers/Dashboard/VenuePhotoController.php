<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Venue\DeleteVenuePhoto;
use App\Actions\Venue\SetCoverVenuePhoto;
use App\Actions\Venue\UploadVenuePhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UploadVenuePhotoRequest;
use App\Models\User;
use App\Models\VenuePhoto;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VenuePhotoController extends Controller
{
    public function store(
        UploadVenuePhotoRequest $request,
        VenueContext $venueContext,
        UploadVenuePhoto $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $request->file('photo'), $request->validated());

        return back()->with('success', 'Foto berhasil diunggah.');
    }

    public function setCover(int $id, Request $request, VenueContext $venueContext, SetCoverVenuePhoto $action): RedirectResponse
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        $photo = VenuePhoto::query()->where('venue_id', $venue->id)->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $photo);

        return back()->with('success', 'Foto sampul venue berhasil diperbarui.');
    }

    public function destroy(int $id, Request $request, VenueContext $venueContext, DeleteVenuePhoto $action): RedirectResponse
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('update', $venue);

        $photo = VenuePhoto::query()->where('venue_id', $venue->id)->findOrFail($id);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $photo);

        return back()->with('success', 'Foto berhasil dihapus.');
    }
}
