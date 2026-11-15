<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use BelongsToVenue, HasUlid;

    public const KIND_DP = 'dp';

    public const KIND_SETTLEMENT = 'settlement';

    public const KIND_FULL = 'full';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_QRIS = 'qris';

    public const METHOD_CASH = 'cash';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'booking_id',
        'venue_id',
        'kind',
        'method',
        'amount',
        'status',
        'proof_path',
        'proof_sha256',
        'proof_mime',
        'proof_size',
        'proof_deleted_at',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'reject_reason',
        'recorded_by',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'proof_size' => 'integer',
            'proof_deleted_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
