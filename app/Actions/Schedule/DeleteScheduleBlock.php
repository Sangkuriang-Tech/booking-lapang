<?php

namespace App\Actions\Schedule;

use App\Actions\Audit\RecordAuditLog;
use App\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeleteScheduleBlock
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, ScheduleBlock $block): void
    {
        DB::transaction(function () use ($actor, $block): void {
            $locked = ScheduleBlock::query()
                ->withoutGlobalScopes()
                ->whereKey($block->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->recordAuditLog->execute(
                action: 'schedule_block.deleted',
                actor: $actor,
                venueId: $locked->venue_id,
                subject: $locked,
                oldValues: [
                    'court_id' => $locked->court_id,
                    'starts_at' => $locked->starts_at->toIso8601String(),
                    'ends_at' => $locked->ends_at->toIso8601String(),
                    'reason' => $locked->reason,
                ],
            );

            $locked->delete();
        });
    }
}
