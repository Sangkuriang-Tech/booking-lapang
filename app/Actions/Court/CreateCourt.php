<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCourt
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, array $data): Court
    {
        $subscription = $venue->activeSubscription();

        if ($subscription === null || $subscription->isInactiveOn(timezone: $venue->timezone)) {
            throw ValidationException::withMessages([
                'venue_id' => ['Langganan venue tidak aktif.'],
            ]);
        }

        $activeCourtsCount = Court::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->whereNull('deleted_at')
            ->count();

        if ($subscription->max_courts_per_venue !== null && $activeCourtsCount >= $subscription->max_courts_per_venue) {
            throw ValidationException::withMessages([
                'name' => ['Kuota lapangan per venue pada paket langganan sudah penuh. Silakan upgrade paket untuk menambah lapangan.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $venue, $data): Court {
            $court = Court::query()->create([
                'venue_id' => $venue->id,
                'sport_id' => $data['sport_id'],
                'category_id' => $data['category_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            $this->recordAuditLog->execute(
                action: 'court.created',
                actor: $actor,
                venueId: $venue->id,
                subject: $court,
                newValues: ['name' => $court->name],
            );

            return $court;
        });
    }
}
