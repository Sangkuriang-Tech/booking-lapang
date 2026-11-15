<?php

namespace App\Actions\Landing;

use App\Models\Scopes\VenueScope;
use App\Models\Subscription;
use App\Models\Venue;
use Illuminate\Support\Collection;

class GetPublicVenues
{
    /**
     * Retrieve all published venues whose owner currently has an active subscription.
     *
     * @return Collection<int, Venue>
     */
    public function execute(bool $withDetails = false): Collection
    {
        $query = Venue::query()
            ->where('is_published', true)
            ->orderBy('name');

        if ($withDetails) {
            $query->with([
                'photos' => static function ($photoQuery): void {
                    $photoQuery->withoutGlobalScope(VenueScope::class)
                        ->orderByDesc('is_cover')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
                'courts' => static function ($courtQuery): void {
                    $courtQuery->withoutGlobalScope(VenueScope::class)
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->with([
                            'sport:id,slug,name',
                            'priceRules:id,court_id,price_per_hour',
                        ]);
                },
            ]);
        }

        $venues = $query->get();

        if ($venues->isEmpty()) {
            return collect();
        }

        $ownerIds = $venues->pluck('owner_id')->unique()->values();

        $latestSubscriptionsByOwner = Subscription::query()
            ->whereIn('owner_id', $ownerIds)
            ->orderByDesc('ends_on')
            ->orderByDesc('id')
            ->get()
            ->groupBy('owner_id')
            ->map(fn (Collection $items): ?Subscription => $items->first());

        return $venues->filter(function (Venue $venue) use ($latestSubscriptionsByOwner): bool {
            /** @var Subscription|null $subscription */
            $subscription = $latestSubscriptionsByOwner->get($venue->owner_id);

            if ($subscription === null) {
                return false;
            }

            return $subscription->allowsPublicListing(null, $venue->timezone ?: 'Asia/Jakarta');
        })->values();
    }
}
