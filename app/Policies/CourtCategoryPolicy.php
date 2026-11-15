<?php

namespace App\Policies;

use App\Models\CourtCategory;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class CourtCategoryPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function view(User $user, CourtCategory $category): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $category->venue_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $this->isOwnerOfCurrentVenue($user);
    }

    public function update(User $user, CourtCategory $category): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $category->venue_id);
    }

    public function delete(User $user, CourtCategory $category): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $category->venue_id);
    }
}
