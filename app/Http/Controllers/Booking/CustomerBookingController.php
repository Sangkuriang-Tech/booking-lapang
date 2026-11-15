<?php

namespace App\Http\Controllers\Booking;

use App\Actions\Booking\CancelBooking;
use App\Actions\Booking\HoldSlot;
use App\Actions\Booking\SubmitPaymentProof;
use App\Http\Controllers\Controller;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\HoldSlotRequest;
use App\Http\Requests\Booking\SubmitPaymentProofRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\Payment;
use App\Models\Scopes\VenueScope;
use App\Models\VenuePaymentAccount;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerBookingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $bookings = Booking::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $user->id)
            ->with([
                'venue:id,ulid,slug,name,city,timezone',
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
            ])
            ->orderByDesc('starts_at')
            ->paginate(15)
            ->through(fn (Booking $booking): array => [
                'ulid' => $booking->ulid,
                'code' => $booking->code,
                'status' => $booking->status,
                'play_date' => $booking->play_date->toDateString(),
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
                'price_total' => $booking->price_total,
                'payment_mode' => $booking->payment_mode,
                'dp_required' => $booking->dp_required,
                'paid_total' => $booking->paid_total,
                'hold_expires_at' => $booking->hold_expires_at?->toIso8601String(),
                'venue' => [
                    'ulid' => $booking->venue->ulid,
                    'slug' => $booking->venue->slug,
                    'name' => $booking->venue->name,
                    'city' => $booking->venue->city,
                    'timezone' => $booking->venue->timezone,
                ],
                'court' => [
                    'ulid' => $booking->court->ulid,
                    'name' => $booking->court->name,
                ],
            ]);

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
        ]);
    }

    public function show(Request $request, string $ulid): Response
    {
        $booking = Booking::query()
            ->withoutGlobalScopes()
            ->where('ulid', $ulid)
            ->with([
                'venue:id,ulid,slug,name,city,address,timezone,cancel_min_hours,dp_percent',
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
                'payments' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->orderByDesc('id'),
            ])
            ->firstOrFail();

        Gate::authorize('view', $booking);

        $paymentAccounts = VenuePaymentAccount::query()
            ->withoutGlobalScope(VenueScope::class)
            ->where('venue_id', $booking->venue_id)
            ->where('is_active', true)
            ->where('visible_from', '<=', now())
            ->orderBy('id')
            ->get()
            ->map(fn (VenuePaymentAccount $account): array => [
                'id' => $account->id,
                'kind' => $account->kind,
                'bank_name' => $account->bank_name,
                'account_number' => $account->account_number,
                'account_holder' => $account->account_holder,
                'qris_image_url' => $account->kind === VenuePaymentAccount::KIND_QRIS
                    ? route('venues.payment-accounts.qris', ['venue' => $booking->venue->ulid, 'account' => $account->id])
                    : null,
            ])
            ->values()
            ->all();

        return Inertia::render('Bookings/Show', [
            'booking' => [
                'ulid' => $booking->ulid,
                'code' => $booking->code,
                'status' => $booking->status,
                'status_reason' => $booking->status_reason,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'play_date' => $booking->play_date->toDateString(),
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
                'price_total' => $booking->price_total,
                'payment_mode' => $booking->payment_mode,
                'dp_required' => $booking->dp_required,
                'paid_total' => $booking->paid_total,
                'hold_expires_at' => $booking->hold_expires_at?->toIso8601String(),
                'venue' => [
                    'ulid' => $booking->venue->ulid,
                    'slug' => $booking->venue->slug,
                    'name' => $booking->venue->name,
                    'city' => $booking->venue->city,
                    'address' => $booking->venue->address,
                    'timezone' => $booking->venue->timezone,
                    'cancel_min_hours' => $booking->venue->cancel_min_hours,
                ],
                'court' => [
                    'ulid' => $booking->court->ulid,
                    'name' => $booking->court->name,
                ],
                'payments' => $booking->payments->map(fn (Payment $payment): array => [
                    'ulid' => $payment->ulid,
                    'kind' => $payment->kind,
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'reject_reason' => $payment->reject_reason,
                    'submitted_at' => $payment->submitted_at?->toIso8601String(),
                    'proof_url' => $payment->proof_path !== null
                        ? route('payments.proof.show', ['payment' => $payment->ulid])
                        : null,
                ])->values()->all(),
            ],
            'payment_accounts' => $paymentAccounts,
        ]);
    }

    public function store(HoldSlotRequest $request, HoldSlot $holdSlot): RedirectResponse
    {
        $validated = $request->validated();

        $court = Court::query()
            ->withoutGlobalScopes()
            ->where('ulid', $validated['court_ulid'])
            ->firstOrFail();

        $booking = $holdSlot->execute(
            customer: $request->user(),
            court: $court,
            startsAt: CarbonImmutable::parse($validated['starts_at'])->utc(),
            endsAt: CarbonImmutable::parse($validated['ends_at'])->utc(),
            data: [
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'payment_mode' => $validated['payment_mode'],
                'idempotency_key' => $validated['idempotency_key'] ?? null,
                'ip' => $request->ip(),
            ],
        );

        return redirect()
            ->route('bookings.show', ['ulid' => $booking->ulid])
            ->with('success', 'Slot berhasil ditahan. Silakan lakukan pembayaran sebelum batas waktu berakhir.');
    }

    public function submitProof(
        SubmitPaymentProofRequest $request,
        string $ulid,
        SubmitPaymentProof $submitPaymentProof,
    ): RedirectResponse {
        $booking = Booking::query()
            ->withoutGlobalScopes()
            ->where('ulid', $ulid)
            ->firstOrFail();

        Gate::authorize('view', $booking);

        $validated = $request->validated();

        $submitPaymentProof->execute(
            customer: $request->user(),
            booking: $booking,
            file: $request->file('proof'),
            data: [
                'method' => $validated['method'],
                'note' => $validated['note'] ?? null,
            ],
        );

        return redirect()
            ->route('bookings.show', ['ulid' => $booking->ulid])
            ->with('success', 'Bukti pembayaran berhasil diunggah dan sedang menunggu validasi.');
    }

    public function cancel(
        CancelBookingRequest $request,
        string $ulid,
        CancelBooking $cancelBooking,
    ): RedirectResponse {
        $booking = Booking::query()
            ->withoutGlobalScopes()
            ->where('ulid', $ulid)
            ->firstOrFail();

        Gate::authorize('cancel', $booking);

        $cancelBooking->execute(
            actor: $request->user(),
            booking: $booking,
            reason: $request->validated('reason'),
        );

        return redirect()
            ->route('bookings.show', ['ulid' => $booking->ulid])
            ->with('success', 'Pesanan berhasil dibatalkan.');
    }
}
