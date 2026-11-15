<?php

namespace App\Actions\Pricing;

use App\Actions\Audit\RecordAuditLog;
use App\Models\PriceRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeletePriceRule
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, PriceRule $rule): void
    {
        $court = $rule->court()->withoutGlobalScopes()->firstOrFail();

        DB::transaction(function () use ($actor, $rule, $court): void {
            $oldValues = $rule->only(['court_id', 'day_type', 'start_min', 'end_min', 'price_per_hour']);

            $rule->delete();

            $this->recordAuditLog->execute(
                action: 'price_rule.deleted',
                actor: $actor,
                venueId: $court->venue_id,
                oldValues: $oldValues,
            );
        });
    }
}
