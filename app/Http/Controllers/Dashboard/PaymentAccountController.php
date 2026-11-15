<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\PaymentAccount\CreatePaymentAccount;
use App\Actions\PaymentAccount\TogglePaymentAccountStatus;
use App\Actions\PaymentAccount\UpdatePaymentAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StorePaymentAccountRequest;
use App\Http\Requests\Dashboard\TogglePaymentAccountRequest;
use App\Http\Requests\Dashboard\UpdatePaymentAccountRequest;
use App\Models\User;
use App\Models\VenuePaymentAccount;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentAccountController extends Controller
{
    public function index(Request $request, VenueContext $venueContext): Response
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('create', VenuePaymentAccount::class);

        $accounts = VenuePaymentAccount::query()
            ->where('venue_id', $venue->id)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->get()
            ->map(fn (VenuePaymentAccount $account): array => [
                'id' => $account->id,
                'kind' => $account->kind,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_holder' => $account->account_holder,
                'qris_url' => $account->kind === VenuePaymentAccount::KIND_QRIS
                    ? route('venues.payment-accounts.qris', ['venueUlid' => $venue->ulid, 'accountId' => $account->id])
                    : null,
                'is_active' => $account->is_active,
                'visible_from' => $account->visible_from->toIso8601String(),
                'is_delayed' => $account->visible_from->isFuture(),
            ])
            ->values()
            ->all();

        $subscription = $venue->activeSubscription();
        $activeCount = collect($accounts)->where('is_active', true)->count();

        return Inertia::render('Dashboard/PaymentAccounts/Index', [
            'accounts' => $accounts,
            'quota' => [
                'used' => $activeCount,
                'max' => $subscription?->max_payment_accounts_per_venue,
            ],
        ]);
    }

    public function store(
        StorePaymentAccountRequest $request,
        VenueContext $venueContext,
        CreatePaymentAccount $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);
        $this->authorize('create', VenuePaymentAccount::class);

        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        if ($request->hasFile('qris_image')) {
            $payload['qris_image'] = $request->file('qris_image');
        }

        $action->execute($actor, $venue, $payload, requireReauth: true);

        return back()->with('success', 'Rekening/QRIS tujuan berhasil ditambahkan.');
    }

    public function update(
        UpdatePaymentAccountRequest $request,
        int $id,
        VenueContext $venueContext,
        UpdatePaymentAccount $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);

        $account = VenuePaymentAccount::query()->where('venue_id', $venue->id)->findOrFail($id);
        $this->authorize('update', $account);

        /** @var User $actor */
        $actor = $request->user();
        $payload = $request->validated();
        if ($request->hasFile('qris_image')) {
            $payload['qris_image'] = $request->file('qris_image');
        }

        $action->execute($actor, $venue, $account, $payload);

        return back()->with('success', 'Rekening/QRIS tujuan berhasil diperbarui dan akan tampil setelah jeda keamanan 24 jam.');
    }

    public function toggle(
        TogglePaymentAccountRequest $request,
        int $id,
        VenueContext $venueContext,
        TogglePaymentAccountStatus $action,
    ): RedirectResponse {
        $venue = $venueContext->venue();
        abort_if($venue === null, 404);

        $account = VenuePaymentAccount::query()->where('venue_id', $venue->id)->findOrFail($id);
        $this->authorize('update', $account);

        /** @var User $actor */
        $actor = $request->user();
        $action->execute(
            $actor,
            $venue,
            $account,
            (bool) $request->validated('is_active'),
            $request->validated('current_password'),
        );

        return back()->with('success', 'Status rekening/QRIS berhasil diperbarui.');
    }
}
