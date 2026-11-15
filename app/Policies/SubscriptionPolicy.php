<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, Subscription $subscription): bool
    {
        return $user->isSuperadmin() || $subscription->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Subscription $subscription): bool
    {
        return $user->isSuperadmin();
    }
}
