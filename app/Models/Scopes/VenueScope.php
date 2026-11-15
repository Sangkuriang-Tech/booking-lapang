<?php

namespace App\Models\Scopes;

use App\Support\VenueContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class VenueScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(VenueContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $user = Auth::user();

        if ($user !== null && $user->isSuperadmin()) {
            $activeVenueId = $context->id();
            if ($activeVenueId !== null) {
                $builder->where($model->qualifyColumn('venue_id'), $activeVenueId);
            }

            return;
        }

        $activeVenueId = $context->id();

        if ($activeVenueId !== null) {
            $builder->where($model->qualifyColumn('venue_id'), $activeVenueId);

            return;
        }

        // Fail closed for authenticated staff/owners who have not selected a valid venue.
        if ($user !== null && $user->isStaff()) {
            $builder->whereRaw('1 = 0');
        }
    }
}
