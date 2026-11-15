<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateSubscriptionPlan
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, SubscriptionPlan $plan, array $data): SubscriptionPlan
    {
        return DB::transaction(function () use ($actor, $plan, $data): SubscriptionPlan {
            $oldValues = $plan->only([
                'name',
                'price_monthly',
                'max_venues',
                'max_courts_per_venue',
                'max_staff_total',
                'max_payment_accounts_per_venue',
                'proof_retention_days',
                'can_export_reports',
                'is_active',
            ]);

            $plan->update([
                'name' => trim((string) $data['name']),
                'price_monthly' => (int) $data['price_monthly'],
                'max_venues' => $data['max_venues'] !== null && $data['max_venues'] !== '' ? (int) $data['max_venues'] : null,
                'max_courts_per_venue' => $data['max_courts_per_venue'] !== null && $data['max_courts_per_venue'] !== '' ? (int) $data['max_courts_per_venue'] : null,
                'max_staff_total' => $data['max_staff_total'] !== null && $data['max_staff_total'] !== '' ? (int) $data['max_staff_total'] : null,
                'max_payment_accounts_per_venue' => $data['max_payment_accounts_per_venue'] !== null && $data['max_payment_accounts_per_venue'] !== '' ? (int) $data['max_payment_accounts_per_venue'] : null,
                'proof_retention_days' => (int) $data['proof_retention_days'],
                'can_export_reports' => (bool) ($data['can_export_reports'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]);

            $fresh = $plan->fresh();

            $this->recordAuditLog->execute(
                action: 'subscription_plan.updated',
                actor: $actor,
                subject: $fresh,
                oldValues: $oldValues,
                newValues: $fresh->only(array_keys($oldValues)),
            );

            return $fresh;
        });
    }
}
