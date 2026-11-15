<?php

namespace App\Actions\Court;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\Court;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteCourt
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Court $court): void
    {
        $hasActiveBookings = Booking::withoutGlobalScopes()
            ->where('court_id', $court->id)
            ->whereIn('status', [
                Booking::STATUS_HELD,
                Booking::STATUS_AWAITING_VALIDATION,
                Booking::STATUS_AWAITING_REUPLOAD,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
            ])
            ->where('ends_at', '>', now())
            ->exists();

        if ($hasActiveBookings) {
            throw ValidationException::withMessages([
                'court' => ['Lapangan tidak dapat dihapus karena masih memiliki booking aktif.'],
            ]);
        }

        DB::transaction(function () use ($actor, $court): void {
            $oldValues = $court->only(['name', 'is_active']);

            $court->delete();

            $this->recordAuditLog->execute(
                action: 'court.deleted',
                actor: $actor,
                venueId: $court->venue_id,
                subject: $court,
                oldValues: $oldValues,
            );
        });
    }
}
