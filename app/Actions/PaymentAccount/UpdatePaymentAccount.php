<?php

namespace App\Actions\PaymentAccount;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\PaymentAccount\Concerns\ManagesPaymentAccountSecurity;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UpdatePaymentAccount
{
    use ManagesPaymentAccountSecurity;

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, VenuePaymentAccount $account, array $data): VenuePaymentAccount
    {
        $this->verifyReauthentication($actor, $data['current_password'] ?? null);

        $qrisImagePath = $account->qris_image_path;
        if (($data['qris_image'] ?? null) instanceof UploadedFile) {
            $qrisImagePath = $this->storeValidatedQrisImage($data['qris_image']);
        }

        $visibleFrom = now()->addHours(24);

        $updated = DB::transaction(function () use ($actor, $venue, $account, $data, $qrisImagePath, $visibleFrom): VenuePaymentAccount {
            $oldValues = [
                'kind' => $account->kind,
                'bank_name' => $account->bank_name,
                'account_holder' => $account->account_holder,
                'visible_from' => $account->visible_from->toIso8601String(),
            ];

            if ($account->kind === VenuePaymentAccount::KIND_BANK) {
                $account->update([
                    'bank_name' => $data['bank_name'],
                    'account_number' => $data['account_number'] ?: $account->account_number,
                    'account_holder' => $data['account_holder'],
                    'visible_from' => $visibleFrom,
                ]);
            } else {
                $account->update([
                    'account_holder' => $data['account_holder'] ?? $account->account_holder,
                    'qris_image_path' => $qrisImagePath,
                    'visible_from' => $visibleFrom,
                ]);
            }

            $fresh = $account->fresh();

            $this->recordAuditLog->execute(
                action: 'payment_account.updated',
                actor: $actor,
                venueId: $venue->id,
                subject: $fresh,
                oldValues: $oldValues,
                newValues: [
                    'kind' => $fresh->kind,
                    'bank_name' => $fresh->bank_name,
                    'account_holder' => $fresh->account_holder,
                    'visible_from' => $fresh->visible_from->toIso8601String(),
                ],
            );

            return $fresh;
        });

        $this->notifyOwnersAndSuperadmins($venue, $updated, $actor, 'Perubahan detail rekening/QRIS tujuan');

        return $updated;
    }
}
