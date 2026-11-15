<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VenuePhoto extends Model
{
    use BelongsToVenue;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'court_id',
        'path',
        'alt_text',
        'width',
        'height',
        'is_cover',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }
}
