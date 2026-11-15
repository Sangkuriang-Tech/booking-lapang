<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE_PERIOD = 'grace_period';

    public const STATUS_INACTIVE = 'inactive';

    public const GRACE_PERIOD_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'plan_id',
        'starts_on',
        'ends_on',
        'is_active',
        'max_venues',
        'max_courts_per_venue',
        'max_staff_total',
        'max_payment_accounts_per_venue',
        'proof_retention_days',
        'can_export_reports',
        'note',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'is_active' => 'boolean',
            'max_venues' => 'integer',
            'max_courts_per_venue' => 'integer',
            'max_staff_total' => 'integer',
            'max_payment_accounts_per_venue' => 'integer',
            'proof_retention_days' => 'integer',
            'can_export_reports' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusOn(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): string
    {
        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        $today = ($referenceDate !== null
            ? CarbonImmutable::instance($referenceDate)
            : CarbonImmutable::now($timezone)
        )->setTimezone($timezone)->startOfDay();

        $startsOn = CarbonImmutable::parse($this->starts_on->toDateString(), $timezone)->startOfDay();
        $endsOn = CarbonImmutable::parse($this->ends_on->toDateString(), $timezone)->startOfDay();
        $graceEndsOn = $endsOn->addDays(self::GRACE_PERIOD_DAYS);

        if ($today->betweenIncluded($startsOn, $endsOn)) {
            return self::STATUS_ACTIVE;
        }

        if ($today->gt($endsOn) && $today->lte($graceEndsOn)) {
            return self::STATUS_GRACE_PERIOD;
        }

        return self::STATUS_INACTIVE;
    }

    public function isActiveOn(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return $this->statusOn($referenceDate, $timezone) === self::STATUS_ACTIVE;
    }

    public function isInGracePeriodOn(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return $this->statusOn($referenceDate, $timezone) === self::STATUS_GRACE_PERIOD;
    }

    public function isInactiveOn(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return $this->statusOn($referenceDate, $timezone) === self::STATUS_INACTIVE;
    }

    public function allowsPublicListing(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return $this->isActiveOn($referenceDate, $timezone);
    }

    public function allowsOnlineBooking(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return $this->isActiveOn($referenceDate, $timezone);
    }

    public function allowsOtsBooking(?CarbonInterface $referenceDate = null, string $timezone = 'Asia/Jakarta'): bool
    {
        return in_array($this->statusOn($referenceDate, $timezone), [
            self::STATUS_ACTIVE,
            self::STATUS_GRACE_PERIOD,
        ], true);
    }
}
