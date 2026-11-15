<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Policies\Concerns\ChecksVenueAccess;

class VenueUserPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isOwnerOfCurrentVenue($user);
    }

    public function create(User $user, Venue $venue): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->isActiveVenueOwner($user, $venue->id);
    }

    public function update(User $user, VenueUser $venueUser): bool
    {
        if ($venueUser->role === VenueUser::ROLE_OWNER && $this->isLastOwner($venueUser->venue_id)) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->isActiveVenueOwner($user, $venueUser->venue_id);
    }

    public function delete(User $user, VenueUser $venueUser): bool
    {
        if ($venueUser->role === VenueUser::ROLE_OWNER && $this->isLastOwner($venueUser->venue_id)) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->isActiveVenueOwner($user, $venueUser->venue_id);
    }

    private function isLastOwner(int $venueId): bool
    {
        return VenueUser::query()
            ->where('venue_id', $venueId)
            ->where('role', VenueUser::ROLE_OWNER)
            ->count() <= 1;
    }
}
