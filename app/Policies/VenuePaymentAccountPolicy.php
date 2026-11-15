<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VenuePaymentAccount;
use App\Policies\Concerns\ChecksVenueAccess;

class VenuePaymentAccountPolicy
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

    public function update(User $user, VenuePaymentAccount $account): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $account->venue_id);
    }

    public function delete(User $user, VenuePaymentAccount $account): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueOwner($user, $account->venue_id);
    }
}
