<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Models\Venue;
use App\Support\VenueContext;
use Illuminate\Auth\Access\AuthorizationException;

class SelectActiveVenue
{
    public function __construct(
        private readonly VenueContext $venueContext,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function execute(User $user, int $venueId): Venue
    {
        if (! $user->is_active || (! $user->isStaff() && ! $user->isSuperadmin())) {
            throw new AuthorizationException('Anda tidak memiliki akses ke venue.');
        }

        if (! $this->venueContext->userBelongsToVenue($user, $venueId)) {
            throw new AuthorizationException('Anda tidak terdaftar pada venue yang dipilih.');
        }

        $venue = Venue::query()->findOrFail($venueId);
        $this->venueContext->set($venue->id);

        return $venue;
    }

    public function resolveAfterLogin(User $user): ?Venue
    {
        if (! $user->isStaff()) {
            return null;
        }

        $venues = $user->venues()->whereNull('venues.deleted_at')->get();

        if ($venues->count() === 1) {
            $venue = $venues->first();
            $this->venueContext->set($venue->id);

            return $venue;
        }

        $this->venueContext->clear();

        return null;
    }
}
