<?php

namespace App\Policies;

use App\Models\PriceRule;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class PriceRulePolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $this->isOwnerOfCurrentVenue($user);
    }

    public function update(User $user, PriceRule $priceRule): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $priceRule->court->venue_id);
    }

    public function delete(User $user, PriceRule $priceRule): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $priceRule->court->venue_id);
    }
}
