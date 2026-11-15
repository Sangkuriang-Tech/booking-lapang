<?php

namespace App\Actions\Schedule;

use App\Actions\Audit\RecordAuditLog;
use App\Models\OperatingHour;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateOperatingHours
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  list<array{weekday: int, opens_min: int, closes_min: int, is_closed: bool}>  $hours
     * @return list<OperatingHour>
     */
    public function execute(User $actor, Venue $venue, array $hours): array
    {
        foreach ($hours as $row) {
            $weekday = (int) $row['weekday'];
            $isClosed = (bool) $row['is_closed'];
            $opensMin = (int) $row['opens_min'];
            $closesMin = (int) $row['closes_min'];

            if (! $isClosed && $closesMin <= $opensMin) {
                throw ValidationException::withMessages([
                    'hours' => ["Jam tutup pada hari ke-{$weekday} harus lebih besar dari jam buka."],
                ]);
            }
        }

        return DB::transaction(function () use ($actor, $venue, $hours): array {
            $saved = [];

            foreach ($hours as $row) {
                $record = OperatingHour::query()->updateOrCreate(
                    [
                        'venue_id' => $venue->id,
                        'weekday' => (int) $row['weekday'],
                    ],
                    [
                        'opens_min' => (int) $row['opens_min'],
                        'closes_min' => (int) $row['closes_min'],
                        'is_closed' => (bool) $row['is_closed'],
                    ],
                );

                $saved[] = $record;
            }

            $this->recordAuditLog->execute(
                action: 'operating_hours.updated',
                actor: $actor,
                venueId: $venue->id,
                newValues: ['days_updated' => count($saved)],
            );

            return $saved;
        });
    }
}
