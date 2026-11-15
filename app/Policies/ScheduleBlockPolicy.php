<?php

namespace App\Policies;

use App\Models\ScheduleBlock;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class ScheduleBlockPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function delete(User $user, ScheduleBlock $block): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $block->venue_id);
    }
}
