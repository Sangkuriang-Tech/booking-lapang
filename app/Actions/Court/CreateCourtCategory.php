<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\CourtCategory;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCourtCategory
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Venue $venue, string $name): CourtCategory
    {
        $trimmedName = trim($name);

        $exists = CourtCategory::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->whereRaw('lower(name) = lower(?)', [$trimmedName])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Nama kategori lapangan sudah digunakan di venue ini.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $venue, $trimmedName): CourtCategory {
            $category = CourtCategory::query()->create([
                'venue_id' => $venue->id,
                'name' => $trimmedName,
            ]);

            $this->recordAuditLog->execute(
                action: 'court_category.created',
                actor: $actor,
                venueId: $venue->id,
                subject: $category,
                newValues: ['name' => $category->name],
            );

            return $category;
        });
    }
}
