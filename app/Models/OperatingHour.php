<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Model;

class OperatingHour extends Model
{
    use BelongsToVenue;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'weekday',
        'opens_min',
        'closes_min',
        'is_closed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'opens_min' => 'integer',
            'closes_min' => 'integer',
            'is_closed' => 'boolean',
        ];
    }
}
