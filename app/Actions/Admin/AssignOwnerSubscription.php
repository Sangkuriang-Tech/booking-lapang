<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignOwnerSubscription
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, User $owner, SubscriptionPlan $plan, array $data): Subscription
    {
        if (! $owner->isStaff()) {
            throw ValidationException::withMessages([
                'owner_id' => ['Akun tujuan harus memiliki peran staf/pemilik.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $owner, $plan, $data): Subscription {
            $subscription = Subscription::query()->create([
                'owner_id' => $owner->id,
                'plan_id' => $plan->id,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'is_active' => (bool) ($data['is_active'] ?? true),
                'max_venues' => $plan->max_venues,
                'max_courts_per_venue' => $plan->max_courts_per_venue,
                'max_staff_total' => $plan->max_staff_total,
                'max_payment_accounts_per_venue' => $plan->max_payment_accounts_per_venue,
                'proof_retention_days' => $plan->proof_retention_days,
                'can_export_reports' => $plan->can_export_reports,
                'note' => $data['note'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->recordAuditLog->execute(
                action: 'subscription.assigned',
                actor: $actor,
                subject: $subscription,
                newValues: [
                    'owner_id' => $owner->id,
                    'plan_code' => $plan->code,
                    'starts_on' => $subscription->starts_on->toDateString(),
                    'ends_on' => $subscription->ends_on->toDateString(),
                    'is_active' => $subscription->is_active,
                ],
            );

            return $subscription;
        });
    }
}
