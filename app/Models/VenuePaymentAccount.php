<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePaymentAccount extends Model
{
    use BelongsToVenue;

    public const KIND_BANK = 'bank';

    public const KIND_QRIS = 'qris';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'kind',
        'bank_name',
        'account_number',
        'account_holder',
        'qris_image_path',
        'is_active',
        'visible_from',
        'created_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'account_number',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'is_active' => 'boolean',
            'visible_from' => 'immutable_datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
