<?php

namespace App\Models\Concerns;

use App\Models\Scopes\VenueScope;
use App\Models\Venue;
use App\Support\VenueContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToVenue
{
    public static function bootBelongsToVenue(): void
    {
        static::addGlobalScope(new VenueScope);

        static::creating(function (Model $model): void {
            if (empty($model->venue_id)) {
                $activeVenueId = app(VenueContext::class)->id();
                if ($activeVenueId !== null) {
                    $model->venue_id = $activeVenueId;
                }
            }
        });
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
