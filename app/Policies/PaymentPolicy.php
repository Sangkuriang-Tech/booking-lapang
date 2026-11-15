<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\ChecksVenueAccess;

class PaymentPolicy
{
    use ChecksVenueAccess;

    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $this->isStaffOfCurrentVenue($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isCustomer()) {
            return $payment->booking->customer_id === $user->id;
        }

        return $this->isActiveVenueStaff($user, $payment->venue_id);
    }

    public function review(User $user, Payment $payment): bool
    {
        return $user->isSuperadmin() || $this->isActiveVenueStaff($user, $payment->venue_id);
    }

    public function delete(User $user, Payment $payment): bool
    {
        // Payment records and proofs cannot be deleted manually (T11).
        return false;
    }
}
