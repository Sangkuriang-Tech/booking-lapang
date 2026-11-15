<?php

namespace App\Actions\Venue;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\VenuePhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetCoverVenuePhoto
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, VenuePhoto $photo): VenuePhoto
    {
        if ($photo->court_id !== null) {
            throw ValidationException::withMessages([
                'photo' => ['Hanya foto utama venue yang dapat dijadikan sampul.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $photo): VenuePhoto {
            VenuePhoto::withoutGlobalScopes()
                ->where('venue_id', $photo->venue_id)
                ->whereNull('court_id')
                ->where('is_cover', true)
                ->update(['is_cover' => false]);

            $photo->update(['is_cover' => true]);

            $this->recordAuditLog->execute(
                action: 'venue_photo.cover_updated',
                actor: $actor,
                venueId: $photo->venue_id,
                subject: $photo,
                newValues: ['path' => $photo->path],
            );

            return $photo->fresh();
        });
    }
}
