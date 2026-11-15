<?php

namespace App\Actions\Venue;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateVenueSettings
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, array $data): Venue
    {
        return DB::transaction(function () use ($actor, $venue, $data): Venue {
            $oldValues = $venue->only([
                'name',
                'description',
                'city',
                'address',
                'contact_phone',
                'timezone',
                'hold_minutes',
                'dp_percent',
                'cancel_min_hours',
                'reupload_minutes',
                'is_published',
            ]);

            $city = $data['city'] ?? $venue->city;

            $venue->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'city' => $city,
                'city_slug' => Str::slug($city),
                'address' => $data['address'],
                'contact_phone' => $data['contact_phone'] ?? null,
                'timezone' => $data['timezone'] ?? $venue->timezone,
                'hold_minutes' => (int) $data['hold_minutes'],
                'dp_percent' => (int) $data['dp_percent'],
                'cancel_min_hours' => (int) $data['cancel_min_hours'],
                'reupload_minutes' => (int) $data['reupload_minutes'],
                'is_published' => (bool) ($data['is_published'] ?? false),
            ]);

            $newValues = $venue->fresh()->only(array_keys($oldValues));

            $this->recordAuditLog->execute(
                action: 'venue.settings_updated',
                actor: $actor,
                venueId: $venue->id,
                subject: $venue,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $venue->fresh();
        });
    }
}
