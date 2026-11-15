<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Support\VenueContext;

trait ChecksVenueAccess
{
    protected function activeVenueId(): ?int
    {
        return app(VenueContext::class)->id();
    }

    protected function isActiveVenueStaff(User $user, int $venueId): bool
    {
        return $user->is_active
            && $user->isStaff()
            && $this->activeVenueId() === $venueId
            && $user->hasVenueAccess($venueId);
    }

    protected function isActiveVenueOwner(User $user, int $venueId): bool
    {
        return $user->is_active
            && $user->isStaff()
            && $this->activeVenueId() === $venueId
            && $user->isOwnerOfVenue($venueId);
    }

    protected function isStaffOfCurrentVenue(User $user): bool
    {
        $venueId = $this->activeVenueId();

        return $venueId !== null && $this->isActiveVenueStaff($user, $venueId);
    }

    protected function isOwnerOfCurrentVenue(User $user): bool
    {
        $venueId = $this->activeVenueId();

        return $venueId !== null && $this->isActiveVenueOwner($user, $venueId);
    }
}
