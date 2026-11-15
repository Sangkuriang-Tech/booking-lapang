<?php

namespace App\Actions\Pricing;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Court;
use App\Models\PriceRule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePriceRule
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Court $court, array $data): PriceRule
    {
        $startMin = (int) $data['start_min'];
        $endMin = (int) $data['end_min'];

        if ($endMin <= $startMin) {
            throw ValidationException::withMessages([
                'end_min' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($actor, $court, $data, $startMin, $endMin): PriceRule {
                $rule = PriceRule::query()->create([
                    'court_id' => $court->id,
                    'day_type' => $data['day_type'],
                    'start_min' => $startMin,
                    'end_min' => $endMin,
                    'price_per_hour' => (int) $data['price_per_hour'],
                ]);

                $this->recordAuditLog->execute(
                    action: 'price_rule.created',
                    actor: $actor,
                    venueId: $court->venue_id,
                    subject: $rule,
                    newValues: $rule->only(['court_id', 'day_type', 'start_min', 'end_min', 'price_per_hour']),
                );

                return $rule;
            });
        } catch (QueryException $e) {
            if ($this->isExclusionViolation($e)) {
                throw ValidationException::withMessages([
                    'start_min' => ['Rentang jam aturan harga bertabrakan dengan aturan lain pada hari yang sama.'],
                ]);
            }

            throw $e;
        }
    }

    private function isExclusionViolation(QueryException $e): bool
    {
        return $e->getCode() === '23P01' || str_contains($e->getMessage(), '23P01');
    }
}
