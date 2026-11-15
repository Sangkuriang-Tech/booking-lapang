<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Court extends Model
{
    use BelongsToVenue, HasUlid, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'venue_id',
        'sport_id',
        'category_id',
        'name',
        'description',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function sport(): BelongsTo
    {
        return $this->belongsTo(Sport::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CourtCategory::class, 'category_id');
    }

    public function priceRules(): HasMany
    {
        return $this->hasMany(PriceRule::class);
    }

    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
