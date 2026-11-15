<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlid, Notifiable;

    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_STAFF = 'staff';

    public const ROLE_SUPERADMIN = 'superadmin';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'role',
        'name',
        'email',
        'phone',
        'email_verified_at',
        'google_id',
        'password',
        'is_active',
        'last_login_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'phone' => 'encrypted',
        ];
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function venues(): BelongsToMany
    {
        return $this->belongsToMany(Venue::class, 'venue_users')
            ->using(VenueUser::class)
            ->withPivot(['role', 'created_at']);
    }

    public function ownedVenues(): HasMany
    {
        return $this->hasMany(Venue::class, 'owner_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'owner_id');
    }

    public function latestSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->first();
    }

    public function isOwnerOfVenue(int $venueId): bool
    {
        return $this->venues()
            ->where('venues.id', $venueId)
            ->wherePivot('role', VenueUser::ROLE_OWNER)
            ->exists();
    }

    public function hasVenueAccess(int $venueId): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->venues()
            ->where('venues.id', $venueId)
            ->exists();
    }
}
