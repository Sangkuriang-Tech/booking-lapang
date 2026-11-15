<?php

namespace App\Policies;

use App\Models\OperatingHour;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class OperatingHourPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function update(User $user, OperatingHour $operatingHour): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $operatingHour->venue_id);
    }
}
