<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Booking\CheckInBooking;
use App\Actions\Booking\CompleteBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CheckInBookingRequest;
use App\Http\Requests\Dashboard\ScanQrRequest;
use App\Models\Booking;
use App\Models\Scopes\VenueScope;
use App\Support\VenueContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckInController extends Controller
{
    public function index(Request $request, VenueContext $venueContext): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $venue = $venueContext->venue();
        $timezone = $venue?->timezone ?: 'Asia/Jakarta';
        $today = now()->setTimezone($timezone)->toDateString();

        $todayBookings = Booking::query()
            ->where('play_date', $today)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_CHECKED_IN])
            ->with([
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $booking): array => [
                'ulid' => $booking->ulid,
                'code' => $booking->code,
                'status' => $booking->status,
                'source' => $booking->source,
                'customer_name' => $booking->customer_name,
                'customer_phone' => $booking->customer_phone,
                'court_name' => $booking->court->name,
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
                'price_total' => $booking->price_total,
                'paid_total' => $booking->paid_total,
                'remaining_amount' => max(0, $booking->price_total - $booking->paid_total),
                'timezone' => $timezone,
            ])
            ->values()
            ->all();

        $scannedBooking = null;
        $scannedUlid = $request->session()->get('scanned_booking_ulid');
        if (is_string($scannedUlid) && $scannedUlid !== '') {
            $found = Booking::query()
                ->where('ulid', $scannedUlid)
                ->with([
                    'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
                ])
                ->first();

            if ($found !== null) {
                $scannedBooking = [
                    'ulid' => $found->ulid,
                    'code' => $found->code,
                    'status' => $found->status,
                    'customer_name' => $found->customer_name,
                    'customer_phone' => $found->customer_phone,
                    'court_name' => $found->court->name,
                    'starts_at' => $found->starts_at->toIso8601String(),
                    'ends_at' => $found->ends_at->toIso8601String(),
                    'price_total' => $found->price_total,
                    'paid_total' => $found->paid_total,
                    'remaining_amount' => max(0, $found->price_total - $found->paid_total),
                    'payment_mode' => $found->payment_mode,
                    'timezone' => $timezone,
                ];
            }
        }

        return Inertia::render('Dashboard/CheckIn/Index', [
            'todayBookings' => $todayBookings,
            'scannedBooking' => $scannedBooking,
        ]);
    }

    public function lookup(ScanQrRequest $request, CheckInBooking $checkInBooking, VenueContext $venueContext): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $venueId = $venueContext->id();
        abort_if($venueId === null, 403);
        $booking = $checkInBooking->lookupByToken($venueId, $request->validated('token'));

        return redirect()
            ->route('dashboard.check-in.index')
            ->with('scanned_booking_ulid', $booking->ulid);
    }

    public function store(CheckInBookingRequest $request, CheckInBooking $checkInBooking, VenueContext $venueContext): RedirectResponse
    {
        Gate::authorize('viewAny', Booking::class);

        $venueId = $venueContext->id();
        abort_if($venueId === null, 403);
        $booking = $checkInBooking->execute(
            $request->user(),
            $venueId,
            $request->validated('token'),
            $request->validated('settlement_method'),
            $request->validated('settlement_amount') !== null ? (int) $request->validated('settlement_amount') : null,
            $request->validated('note'),
        );

        return redirect()
            ->route('dashboard.check-in.index')
            ->with('success', "Check-in booking {$booking->code} berhasil dicatat.");
    }

    public function complete(Request $request, string $ulid, CompleteBooking $completeBooking): RedirectResponse
    {
        $booking = Booking::query()
            ->where('ulid', $ulid)
            ->firstOrFail();

        Gate::authorize('checkIn', $booking);

        $completeBooking->execute($request->user(), $booking);

        return redirect()
            ->route('dashboard.check-in.index')
            ->with('success', "Booking {$booking->code} ditandai selesai.");
    }
}
