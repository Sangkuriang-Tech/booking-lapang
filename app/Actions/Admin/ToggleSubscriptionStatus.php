<?php

namespace App\Actions\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ToggleSubscriptionStatus
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Subscription $subscription, bool $isActive, ?string $endsOn = null): Subscription
    {
        return DB::transaction(function () use ($actor, $subscription, $isActive, $endsOn): Subscription {
            $oldValues = [
                'is_active' => $subscription->is_active,
                'ends_on' => $subscription->ends_on->toDateString(),
            ];

            $payload = ['is_active' => $isActive];
            if ($endsOn !== null) {
                $payload['ends_on'] = $endsOn;
            }

            $subscription->update($payload);
            $fresh = $subscription->fresh();

            $this->recordAuditLog->execute(
                action: 'subscription.status_updated',
                actor: $actor,
                subject: $fresh,
                oldValues: $oldValues,
                newValues: [
                    'is_active' => $fresh->is_active,
                    'ends_on' => $fresh->ends_on->toDateString(),
                ],
            );

            return $fresh;
        });
    }
}
