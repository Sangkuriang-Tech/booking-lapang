<?php

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venue extends Model
{
    use HasUlid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'owner_id',
        'slug',
        'name',
        'description',
        'city',
        'city_slug',
        'address',
        'latitude',
        'longitude',
        'contact_phone',
        'timezone',
        'hold_minutes',
        'dp_percent',
        'cancel_min_hours',
        'reupload_minutes',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'hold_minutes' => 'integer',
            'dp_percent' => 'integer',
            'cancel_min_hours' => 'integer',
            'reupload_minutes' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'venue_users')
            ->using(VenueUser::class)
            ->withPivot(['role', 'created_at']);
    }

    public function courtCategories(): HasMany
    {
        return $this->hasMany(CourtCategory::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(VenuePhoto::class);
    }

    public function operatingHours(): HasMany
    {
        return $this->hasMany(OperatingHour::class);
    }

    public function paymentAccounts(): HasMany
    {
        return $this->hasMany(VenuePaymentAccount::class);
    }

    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return Subscription::query()
            ->where('owner_id', $this->owner_id)
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->first();
    }

    public function subscriptionStatus(?CarbonInterface $referenceDate = null): string
    {
        $subscription = $this->activeSubscription();

        if ($subscription === null) {
            return Subscription::STATUS_INACTIVE;
        }

        return $subscription->statusOn($referenceDate, $this->timezone ?: 'Asia/Jakarta');
    }

    public function allowsPublicListing(?CarbonInterface $referenceDate = null): bool
    {
        if (! $this->is_published || $this->trashed()) {
            return false;
        }

        $subscription = $this->activeSubscription();

        return $subscription !== null && $subscription->allowsPublicListing($referenceDate, $this->timezone ?: 'Asia/Jakarta');
    }

    public function allowsOnlineBooking(?CarbonInterface $referenceDate = null): bool
    {
        return $this->allowsPublicListing($referenceDate);
    }

    public function allowsOtsBooking(?CarbonInterface $referenceDate = null): bool
    {
        $subscription = $this->activeSubscription();

        return $subscription !== null && $subscription->allowsOtsBooking($referenceDate, $this->timezone ?: 'Asia/Jakarta');
    }
}
