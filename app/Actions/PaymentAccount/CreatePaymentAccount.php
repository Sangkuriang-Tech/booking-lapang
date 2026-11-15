<?php

namespace App\Actions\PaymentAccount;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentAccount
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, array $data): VenuePaymentAccount
    {
        $subscription = $venue->activeSubscription();

        if ($subscription === null || $subscription->isInactiveOn(timezone: $venue->timezone)) {
            throw ValidationException::withMessages([
                'venue_id' => ['Langganan venue tidak aktif.'],
            ]);
        }

        $activeAccountsCount = VenuePaymentAccount::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->where('is_active', true)
            ->count();

        if (
            $subscription->max_payment_accounts_per_venue !== null
            && $activeAccountsCount >= $subscription->max_payment_accounts_per_venue
        ) {
            throw ValidationException::withMessages([
                'kind' => ['Kuota rekening/QRIS aktif pada paket langganan sudah penuh. Silakan upgrade paket untuk menambah rekening tujuan.'],
            ]);
        }

        $hasAnyAccount = VenuePaymentAccount::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->exists();

        $visibleFrom = $hasAnyAccount ? now()->addHours(24) : now();

        return DB::transaction(function () use ($actor, $venue, $data, $visibleFrom): VenuePaymentAccount {
            $account = VenuePaymentAccount::query()->create([
                'venue_id' => $venue->id,
                'kind' => $data['kind'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'account_holder' => $data['account_holder'] ?? null,
                'qris_image_path' => $data['qris_image_path'] ?? null,
                'is_active' => true,
                'visible_from' => $visibleFrom,
                'created_by' => $actor->id,
            ]);

            $this->recordAuditLog->execute(
                action: 'payment_account.created',
                actor: $actor,
                venueId: $venue->id,
                subject: $account,
                newValues: [
                    'kind' => $account->kind,
                    'bank_name' => $account->bank_name,
                    'visible_from' => $account->visible_from->toIso8601String(),
                ],
            );

            return $account;
        });
    }
}
