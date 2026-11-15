<?php

namespace App\Support;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

class VenueContext
{
    public const SESSION_KEY = 'active_venue_id';

    private ?int $resolvedVenueId = null;

    private bool $bypassed = false;

    public function id(): ?int
    {
        if ($this->resolvedVenueId !== null) {
            return $this->resolvedVenueId;
        }

        if (! app()->bound('request')) {
            return null;
        }

        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $sessionVenueId = $request->session()->get(self::SESSION_KEY);

        if (! is_numeric($sessionVenueId)) {
            return null;
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        $venueId = (int) $sessionVenueId;

        if (! $this->userBelongsToVenue($user, $venueId)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $this->resolvedVenueId = $venueId;

        return $this->resolvedVenueId;
    }

    public function venue(): ?Venue
    {
        $id = $this->id();

        return $id !== null ? Venue::query()->find($id) : null;
    }

    public function set(int $venueId): void
    {
        $this->resolvedVenueId = $venueId;

        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->put(self::SESSION_KEY, $venueId);
        }
    }

    public function clear(): void
    {
        $this->resolvedVenueId = null;

        if (app()->bound('request') && request()->hasSession()) {
            request()->session()->forget(self::SESSION_KEY);
        }
    }

    public function userBelongsToVenue(User $user, int $venueId): bool
    {
        if ($user->isSuperadmin()) {
            return Venue::query()->whereKey($venueId)->exists();
        }

        return DB::table('venue_users')
            ->join('venues', 'venues.id', '=', 'venue_users.venue_id')
            ->where('venue_users.user_id', $user->id)
            ->where('venue_users.venue_id', $venueId)
            ->whereNull('venues.deleted_at')
            ->exists();
    }

    public function isBypassed(): bool
    {
        return $this->bypassed;
    }

    /**
     * Execute a callback without applying the active venue global scope.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withoutScope(callable $callback): mixed
    {
        $previous = $this->bypassed;
        $this->bypassed = true;

        try {
            return $callback();
        } finally {
            $this->bypassed = $previous;
        }
    }
}
