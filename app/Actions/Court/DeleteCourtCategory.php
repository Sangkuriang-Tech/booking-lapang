<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Court;
use App\Models\CourtCategory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCourtCategory
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, CourtCategory $category): void
    {
        $inUse = Court::withoutGlobalScopes()
            ->where('venue_id', $category->venue_id)
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'category' => ['Kategori tidak dapat dihapus karena masih digunakan oleh lapangan aktif.'],
            ]);
        }

        DB::transaction(function () use ($actor, $category): void {
            $oldValues = ['name' => $category->name];
            $venueId = $category->venue_id;
            $categoryId = $category->id;

            $category->delete();

            $this->recordAuditLog->execute(
                action: 'court_category.deleted',
                actor: $actor,
                venueId: $venueId,
                oldValues: [...$oldValues, 'id' => $categoryId],
            );
        });
    }
}
