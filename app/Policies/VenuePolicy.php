<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venue;
use App\Policies\Concerns\ChecksVenueAccess;

class VenuePolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function view(User $user, Venue $venue): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->isActiveVenueStaff($user, $venue->id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Venue $venue): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $this->isActiveVenueOwner($user, $venue->id);
    }

    public function delete(User $user, Venue $venue): bool
    {
        return $user->isSuperadmin();
    }
}
