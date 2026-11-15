<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRule extends Model
{
    public const DAY_TYPE_WEEKDAY = 'weekday';

    public const DAY_TYPE_WEEKEND = 'weekend';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'court_id',
        'day_type',
        'start_min',
        'end_min',
        'price_per_hour',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_min' => 'integer',
            'end_min' => 'integer',
            'price_per_hour' => 'integer',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
