<?php

namespace App\Actions\Venue;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\VenuePhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteVenuePhoto
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, VenuePhoto $photo): void
    {
        $disk = Storage::disk('public');
        $basePath = preg_replace('/\.webp$/', '', $photo->path);

        $pathsToDelete = array_filter([
            $photo->path,
            $basePath ? $basePath.'-640.webp' : null,
            $basePath ? $basePath.'.avif' : null,
            $basePath ? $basePath.'-640.avif' : null,
        ]);

        DB::transaction(function () use ($actor, $photo, $disk, $pathsToDelete): void {
            $oldValues = ['path' => $photo->path, 'is_cover' => $photo->is_cover];
            $venueId = $photo->venue_id;

            $photo->delete();

            foreach ($pathsToDelete as $path) {
                if ($disk->exists($path)) {
                    $disk->delete($path);
                }
            }

            $this->recordAuditLog->execute(
                action: 'venue_photo.deleted',
                actor: $actor,
                venueId: $venueId,
                oldValues: $oldValues,
            );
        });
    }
}
