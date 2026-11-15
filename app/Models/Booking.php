<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use BelongsToVenue, HasUlid;

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_OTS = 'ots';

    public const PAYMENT_MODE_DP = 'dp';

    public const PAYMENT_MODE_FULL = 'full';

    public const STATUS_HELD = 'held';

    public const STATUS_AWAITING_VALIDATION = 'awaiting_validation';

    public const STATUS_AWAITING_REUPLOAD = 'awaiting_reupload';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    /** @var list<string> */
    public const LOCKED_STATUSES = [
        self::STATUS_HELD,
        self::STATUS_AWAITING_VALIDATION,
        self::STATUS_AWAITING_REUPLOAD,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    /** @var list<string> */
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_CANCELLED,
        self::STATUS_NO_SHOW,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'code',
        'venue_id',
        'court_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'source',
        'status',
        'starts_at',
        'ends_at',
        'play_date',
        'price_total',
        'payment_mode',
        'dp_required',
        'paid_total',
        'hold_expires_at',
        'idempotency_key',
        'qr_token_hash',
        'qr_token_enc',
        'qr_valid_from',
        'qr_valid_until',
        'qr_used_at',
        'qr_revoked_at',
        'confirmed_at',
        'checked_in_at',
        'completed_at',
        'cancelled_at',
        'status_reason',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'qr_token_hash',
        'qr_token_enc',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'customer_phone' => 'encrypted',
            'qr_token_enc' => 'encrypted',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'play_date' => 'immutable_date',
            'price_total' => 'integer',
            'dp_required' => 'integer',
            'paid_total' => 'integer',
            'hold_expires_at' => 'immutable_datetime',
            'qr_valid_from' => 'immutable_datetime',
            'qr_valid_until' => 'immutable_datetime',
            'qr_used_at' => 'immutable_datetime',
            'qr_revoked_at' => 'immutable_datetime',
            'confirmed_at' => 'immutable_datetime',
            'checked_in_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(BookingReminder::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(BookingStatusLog::class);
    }
}
