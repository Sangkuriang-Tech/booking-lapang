<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Staff\AssignVenueUser;
use App\Actions\Staff\RemoveVenueUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AssignStaffRequest;
use App\Models\User;
use App\Models\VenueUser;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StaffManagementController extends Controller
{
    public function index(Request $request, VenueContext $venueContext): Response
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('viewAny', VenueUser::class);

        $members = VenueUser::query()
            ->with('user:id,ulid,name,email,is_active')
            ->where('venue_id', $venue->id)
            ->orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")
            ->get()
            ->map(fn (VenueUser $member): array => [
                'user_ulid' => $member->user?->ulid,
                'name' => $member->user?->name,
                'email' => $member->user?->email,
                'role' => $member->role,
                'is_active' => (bool) $member->user?->is_active,
            ])
            ->values()
            ->all();

        $subscription = $venue->activeSubscription();
        $totalOwnerStaff = (int) DB::table('venue_users')
            ->join('venues', 'venues.id', '=', 'venue_users.venue_id')
            ->where('venues.owner_id', $venue->owner_id)
            ->whereNull('venues.deleted_at')
            ->where('venue_users.role', VenueUser::ROLE_STAFF)
            ->distinct()
            ->count('venue_users.user_id');

        return Inertia::render('Dashboard/Staff/Index', [
            'members' => $members,
            'quota' => [
                'used' => $totalOwnerStaff,
                'max' => $subscription?->max_staff_total,
            ],
        ]);
    }

    public function store(
        AssignStaffRequest $request,
        VenueContext $venueContext,
        AssignVenueUser $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('create', [VenueUser::class, $venue]);

        $email = mb_strtolower(trim((string) $request->validated('email')));
        $targetUser = User::query()
            ->whereRaw('lower(email) = ?', [$email])
            ->where('role', User::ROLE_STAFF)
            ->first();

        if ($targetUser === null) {
            throw ValidationException::withMessages([
                'email' => ['Akun staf/pemilik dengan email tersebut tidak ditemukan. Hubungi Superadmin untuk membuat akun baru.'],
            ]);
        }

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $targetUser, $request->validated('role'));

        return back()->with('success', 'Anggota tim venue berhasil ditambahkan.');
    }

    public function destroy(
        string $userUlid,
        Request $request,
        VenueContext $venueContext,
        RemoveVenueUser $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);

        $targetUser = User::query()->where('ulid', $userUlid)->firstOrFail();
        $membership = VenueUser::query()
            ->where('venue_id', $venue->id)
            ->where('user_id', $targetUser->id)
            ->firstOrFail();

        $this->authorize('delete', $membership);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute($actor, $venue, $targetUser);

        return back()->with('success', 'Anggota tim berhasil dihapus dari venue.');
    }
}
