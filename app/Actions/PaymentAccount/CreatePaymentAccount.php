<?php

namespace App\Actions\PaymentAccount;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\PaymentAccount\Concerns\ManagesPaymentAccountSecurity;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenuePaymentAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreatePaymentAccount
{
    use ManagesPaymentAccountSecurity;

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, array $data, bool $requireReauth = false): VenuePaymentAccount
    {
        if ($requireReauth || array_key_exists('current_password', $data)) {
            $this->verifyReauthentication($actor, $data['current_password'] ?? null);
        }

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

        $qrisImagePath = $data['qris_image_path'] ?? null;
        if (($data['qris_image'] ?? null) instanceof UploadedFile) {
            $qrisImagePath = $this->storeValidatedQrisImage($data['qris_image']);
        }

        $hasAnyAccount = VenuePaymentAccount::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->exists();

        $visibleFrom = $hasAnyAccount ? now()->addHours(24) : now();

        $account = DB::transaction(function () use ($actor, $venue, $data, $qrisImagePath, $visibleFrom): VenuePaymentAccount {
            $account = VenuePaymentAccount::query()->create([
                'venue_id' => $venue->id,
                'kind' => $data['kind'],
                'bank_name' => $data['bank_name'] ?? null,
                'account_number' => $data['account_number'] ?? null,
                'account_holder' => $data['account_holder'] ?? null,
                'qris_image_path' => $qrisImagePath,
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

        $this->notifyOwnersAndSuperadmins($venue, $account, $actor, 'Penambahan rekening/QRIS tujuan baru');

        return $account;
    }
}
