<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Booking\ValidatePayment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ValidatePaymentRequest;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Scopes\VenueScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentValidationController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $bookings = Booking::query()
            ->whereIn('status', [
                Booking::STATUS_AWAITING_VALIDATION,
                Booking::STATUS_AWAITING_REUPLOAD,
            ])
            ->with([
                'venue:id,ulid,name,timezone,reupload_minutes',
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
                'payments' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->orderByDesc('id'),
            ])
            ->orderBy('starts_at')
            ->get();

        $hashes = $bookings
            ->flatMap(fn (Booking $booking) => $booking->payments->pluck('proof_sha256'))
            ->filter()
            ->unique()
            ->values();

        $hashBookingMap = [];
        if ($hashes->isNotEmpty()) {
            $rows = Payment::query()
                ->withoutGlobalScopes()
                ->whereIn('proof_sha256', $hashes)
                ->select('proof_sha256', 'booking_id')
                ->get();

            foreach ($rows as $row) {
                $hashBookingMap[$row->proof_sha256][$row->booking_id] = true;
            }
        }

        $items = $bookings->map(function (Booking $booking) use ($hashBookingMap): array {
            $latestPayment = $booking->payments->first();
            $latestHash = $latestPayment?->proof_sha256;
            $hasDuplicateProof = $latestHash !== null
                && isset($hashBookingMap[$latestHash])
                && count($hashBookingMap[$latestHash]) > 1;

            return [
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
                'timezone' => $booking->venue->timezone ?: 'Asia/Jakarta',
                'reupload_minutes' => $booking->venue->reupload_minutes,
                'court_name' => $booking->court->name,
                'duplicate_proof_warning' => $hasDuplicateProof,
                'latest_payment' => $latestPayment !== null ? [
                    'ulid' => $latestPayment->ulid,
                    'kind' => $latestPayment->kind,
                    'method' => $latestPayment->method,
                    'amount' => $latestPayment->amount,
                    'status' => $latestPayment->status,
                    'submitted_at' => $latestPayment->submitted_at?->toIso8601String(),
                    'proof_url' => $latestPayment->proof_path !== null
                        ? route('payments.proof.show', ['ulid' => $latestPayment->ulid])
                        : null,
                ] : null,
            ];
        })->values()->all();

        return Inertia::render('Dashboard/Validations/Index', [
            'bookings' => $items,
        ]);
    }

    public function update(ValidatePaymentRequest $request, string $ulid, ValidatePayment $validatePayment): RedirectResponse
    {
        $booking = Booking::query()
            ->where('ulid', $ulid)
            ->firstOrFail();

        Gate::authorize('validatePayment', $booking);

        $decision = (string) $request->validated('decision');

        $validatePayment->execute(
            $request->user(),
            $booking,
            $decision,
            $request->validated('reason'),
        );

        $message = match ($decision) {
            ValidatePayment::DECISION_APPROVE => "Booking {$booking->code} disetujui dan tiket QR telah dikirim.",
            ValidatePayment::DECISION_REUPLOAD => "Permintaan bukti baru untuk booking {$booking->code} telah dikirim.",
            default => "Booking {$booking->code} ditolak dan slot telah dilepas.",
        };

        return redirect()
            ->route('dashboard.validations.index')
            ->with('success', $message);
    }
}
