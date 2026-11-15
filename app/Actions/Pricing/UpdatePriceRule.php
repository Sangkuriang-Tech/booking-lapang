<?php

namespace App\Actions\Pricing;

use App\Actions\Audit\RecordAuditLog;
use App\Models\PriceRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePriceRule
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, PriceRule $rule, array $data): PriceRule
    {
        $startMin = (int) $data['start_min'];
        $endMin = (int) $data['end_min'];

        if ($endMin <= $startMin) {
            throw ValidationException::withMessages([
                'end_min' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $court = $rule->court()->withoutGlobalScopes()->firstOrFail();

        try {
            return DB::transaction(function () use ($actor, $rule, $court, $data, $startMin, $endMin): PriceRule {
                $oldValues = $rule->only(['day_type', 'start_min', 'end_min', 'price_per_hour']);

                $rule->update([
                    'day_type' => $data['day_type'],
                    'start_min' => $startMin,
                    'end_min' => $endMin,
                    'price_per_hour' => (int) $data['price_per_hour'],
                ]);

                $newValues = $rule->fresh()->only(array_keys($oldValues));

                $this->recordAuditLog->execute(
                    action: 'price_rule.updated',
                    actor: $actor,
                    venueId: $court->venue_id,
                    subject: $rule,
                    oldValues: $oldValues,
                    newValues: $newValues,
                );

                return $rule->fresh();
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01' || str_contains($e->getMessage(), '23P01')) {
                throw ValidationException::withMessages([
                    'start_min' => ['Rentang jam aturan harga bertabrakan dengan aturan lain pada hari yang sama.'],
                ]);
            }

            throw $e;
        }
    }
}
