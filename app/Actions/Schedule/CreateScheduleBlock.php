<?php

namespace App\Actions\Schedule;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\Court;
use App\Models\ScheduleBlock;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateScheduleBlock
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Court $court, CarbonInterface $startsAt, CarbonInterface $endsAt, string $reason): ScheduleBlock
    {
        try {
            return DB::transaction(function () use ($actor, $court, $startsAt, $endsAt, $reason): ScheduleBlock {
                // Lock the court row to prevent race conditions with concurrent bookings.
                Court::withoutGlobalScopes()
                    ->whereKey($court->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $hasActiveBooking = Booking::withoutGlobalScopes()
                    ->where('court_id', $court->id)
                    ->whereIn('status', Booking::LOCKED_STATUSES)
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt)
                    ->exists();

                if ($hasActiveBooking) {
                    throw ValidationException::withMessages([
                        'slot' => ['Slot sudah memiliki booking aktif dan tidak dapat diblokir.'],
                    ]);
                }

                $block = ScheduleBlock::query()->create([
                    'venue_id' => $court->venue_id,
                    'court_id' => $court->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'reason' => $reason,
                    'created_by' => $actor->id,
                ]);

                $this->recordAuditLog->execute(
                    action: 'schedule_block.created',
                    actor: $actor,
                    venueId: $court->venue_id,
                    subject: $block,
                    newValues: ['court_id' => $court->id, 'reason' => $reason],
                );

                return $block;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages([
                    'slot' => ['Rentang waktu bertabrakan dengan blokir jadwal yang sudah ada.'],
                ]);
            }

            throw $exception;
        }
    }
}
