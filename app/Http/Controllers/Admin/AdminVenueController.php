<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Venue\CreateVenue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminVenueRequest;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminVenueController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('create', Venue::class);

        $venues = Venue::query()
            ->with('owner:id,ulid,name,email')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Venue $venue): array => [
                'ulid' => $venue->ulid,
                'name' => $venue->name,
                'slug' => $venue->slug,
                'city' => $venue->city,
                'timezone' => $venue->timezone,
                'is_published' => $venue->is_published,
                'owner_name' => $venue->owner?->name,
                'owner_email' => $venue->owner?->email,
                'subscription_status' => $venue->subscriptionStatus(),
            ])
            ->values()
            ->all();

        $owners = User::query()
            ->where('role', User::ROLE_STAFF)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['ulid', 'name', 'email']);

        return Inertia::render('Admin/Venues/Index', [
            'venues' => $venues,
            'owners' => $owners,
        ]);
    }

    public function store(StoreAdminVenueRequest $request, CreateVenue $action): RedirectResponse
    {
        $this->authorize('create', Venue::class);

        $owner = User::query()->where('ulid', $request->validated('owner_ulid'))->firstOrFail();

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $owner, $request->validated());

        return back()->with('success', 'Venue baru berhasil dibuat dan ditautkan ke pemilik.');
    }
}
