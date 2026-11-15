<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Court;
use App\Models\CourtCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCourt
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Court $court, array $data): Court
    {
        if (! empty($data['category_id'])) {
            $belongsToVenue = CourtCategory::withoutGlobalScopes()
                ->where('id', $data['category_id'])
                ->where('venue_id', $court->venue_id)
                ->exists();

            if (! $belongsToVenue) {
                throw ValidationException::withMessages([
                    'category_id' => ['Kategori lapangan tidak ditemukan di venue ini.'],
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $court, $data): Court {
            $oldValues = $court->only(['sport_id', 'category_id', 'name', 'description', 'is_active', 'sort_order']);

            $court->update([
                'sport_id' => (int) $data['sport_id'],
                'category_id' => $data['category_id'] ?? null,
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
                'sort_order' => (int) ($data['sort_order'] ?? $court->sort_order),
            ]);

            $newValues = $court->fresh()->only(array_keys($oldValues));

            $this->recordAuditLog->execute(
                action: 'court.updated',
                actor: $actor,
                venueId: $court->venue_id,
                subject: $court,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $court->fresh();
        });
    }
}
