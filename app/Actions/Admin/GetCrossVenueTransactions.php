<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GetCrossVenueTransactions
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function execute(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = Booking::withoutGlobalScopes()
            ->with([
                'venue' => fn ($q) => $q->withoutGlobalScopes(),
                'court' => fn ($q) => $q->withoutGlobalScopes(),
            ])
            ->orderByDesc('created_at');

        if (! empty($filters['venue_ulid'])) {
            $query->whereHas('venue', fn ($q) => $q->where('ulid', $filters['venue_ulid']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['play_date'])) {
            $query->where('play_date', $filters['play_date']);
        }

        $this->recordAuditLog->execute(
            action: 'admin.cross_venue_transactions_viewed',
            actor: $actor,
            newValues: array_filter([
                'venue_ulid' => $filters['venue_ulid'] ?? null,
                'status' => $filters['status'] ?? null,
                'play_date' => $filters['play_date'] ?? null,
            ]),
        );

        return $query->paginate(25)->withQueryString();
    }
}
