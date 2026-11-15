<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\CourtCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateCourtCategory
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, CourtCategory $category, string $name): CourtCategory
    {
        $trimmedName = trim($name);

        $exists = CourtCategory::withoutGlobalScopes()
            ->where('venue_id', $category->venue_id)
            ->where('id', '!=', $category->id)
            ->whereRaw('lower(name) = lower(?)', [$trimmedName])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Nama kategori lapangan sudah digunakan di venue ini.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $category, $trimmedName): CourtCategory {
            $oldName = $category->name;

            $category->update([
                'name' => $trimmedName,
            ]);

            $this->recordAuditLog->execute(
                action: 'court_category.updated',
                actor: $actor,
                venueId: $category->venue_id,
                subject: $category,
                oldValues: ['name' => $oldName],
                newValues: ['name' => $trimmedName],
            );

            return $category->fresh();
        });
    }
}
