<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingReminder extends Model
{
    public const KIND_H_MINUS_1D = 'h_minus_1d';

    public const KIND_H_MINUS_5H = 'h_minus_5h';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'booking_id',
        'kind',
        'send_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'send_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
