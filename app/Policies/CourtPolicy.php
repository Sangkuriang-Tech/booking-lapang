<?php

namespace App\Policies;

use App\Models\Court;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class CourtPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function view(User $user, Court $court): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $court->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $this->isOwnerOfCurrentVenue($user);
    }

    public function update(User $user, Court $court): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $court->venue_id);
    }

    public function delete(User $user, Court $court): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $court->venue_id);
    }
}
