<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourtCategory extends Model
{
    use BelongsToVenue;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'venue_id',
        'name',
    ];

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class, 'category_id');
    }
}
