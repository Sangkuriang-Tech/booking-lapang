<?php

namespace App\Actions\PaymentAccount;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\PaymentAccount\Concerns\ManagesPaymentAccountSecurity;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TogglePaymentAccountStatus
{
    use ManagesPaymentAccountSecurity;

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Venue $venue, VenuePaymentAccount $account, bool $isActive, ?string $currentPassword): VenuePaymentAccount
    {
        $this->verifyReauthentication($actor, $currentPassword);

        if ($isActive && ! $account->is_active) {
            $subscription = $venue->activeSubscription();
            if ($subscription === null || $subscription->isInactiveOn(timezone: $venue->timezone)) {
                throw ValidationException::withMessages([
                    'venue_id' => ['Langganan venue tidak aktif.'],
                ]);
            }

            $activeCount = VenuePaymentAccount::withoutGlobalScopes()
                ->where('venue_id', $venue->id)
                ->where('is_active', true)
                ->count();

            if ($subscription->max_payment_accounts_per_venue !== null && $activeCount >= $subscription->max_payment_accounts_per_venue) {
                throw ValidationException::withMessages([
                    'is_active' => ['Kuota rekening/QRIS aktif pada paket langganan sudah penuh.'],
                ]);
            }
        }

        $updated = DB::transaction(function () use ($actor, $venue, $account, $isActive): VenuePaymentAccount {
            $oldStatus = $account->is_active;

            $account->update([
                'is_active' => $isActive,
                'visible_from' => $isActive && ! $oldStatus ? now()->addHours(24) : $account->visible_from,
            ]);

            $fresh = $account->fresh();

            $this->recordAuditLog->execute(
                action: $isActive ? 'payment_account.activated' : 'payment_account.deactivated',
                actor: $actor,
                venueId: $venue->id,
                subject: $fresh,
                oldValues: ['is_active' => $oldStatus],
                newValues: ['is_active' => $fresh->is_active],
            );

            return $fresh;
        });

        $this->notifyOwnersAndSuperadmins(
            $venue,
            $updated,
            $actor,
            $isActive ? 'Pengaktifan kembali rekening/QRIS tujuan' : 'Penonaktifan rekening/QRIS tujuan',
        );

        return $updated;
    }
}
