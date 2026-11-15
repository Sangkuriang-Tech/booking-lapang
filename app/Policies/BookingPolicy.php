<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Policies\Concerns\ChecksVenueAccess;

class BookingPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin()
            || $user->isCustomer()
            || $this->isStaffOfCurrentVenue($user);
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id;
        }

        return $this->isActiveVenueStaff($user, $booking->venue_id);
    }

    public function createOnline(User $user, Venue $venue): bool
    {
        return $user->is_active
            && $user->isCustomer()
            && $venue->allowsOnlineBooking();
    }

    public function createOts(User $user, Venue $venue): bool
    {
        if ($user->isSuperadmin()) {
            return $venue->allowsOtsBooking();
        }

        return $this->isActiveVenueStaff($user, $venue->id)
            && $venue->allowsOtsBooking();
    }

    public function validatePayment(User $user, Booking $booking): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $booking->venue_id);
    }

    public function checkIn(User $user, Booking $booking): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $booking->venue_id);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            return $booking->customer_id === $user->id;
        }

        return $this->isActiveVenueStaff($user, $booking->venue_id);
    }
}
