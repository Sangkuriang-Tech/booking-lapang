<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Booking\CreateOtsBooking;
use App\Actions\Booking\GetAvailableSlots;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\CreateOtsBookingRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Support\VenueContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OtsBookingController extends Controller
{
    public function create(Request $request, GetAvailableSlots $getAvailableSlots, VenueContext $venueContext): Response
    {
        Gate::authorize('viewAny', Booking::class);

        $venue = $venueContext->venue();
        abort_if($venue === null, 403);
        $timezone = $venue->timezone ?: 'Asia/Jakarta';

        $courts = Court::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'ulid', 'name']);

        $selectedCourtUlid = $request->string('court')->toString();
        $selectedCourt = $courts->firstWhere('ulid', $selectedCourtUlid) ?? $courts->first();

        $date = trim($request->string('date')->toString());
        try {
            $playDate = $date !== ''
                ? CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()
                : CarbonImmutable::now($timezone)->startOfDay();
        } catch (Throwable) {
            $playDate = CarbonImmutable::now($timezone)->startOfDay();
        }

        $slots = $selectedCourt !== null
            ? $getAvailableSlots->execute($venue, $selectedCourt, $playDate)
            : [];

        return Inertia::render('Dashboard/Ots/Create', [
            'allowsOts' => $venue->allowsOtsBooking(),
            'dpPercent' => $venue->dp_percent,
            'timezone' => $timezone,
            'selectedDate' => $playDate->toDateString(),
            'selectedCourtUlid' => $selectedCourt?->ulid,
            'courts' => $courts->map(fn (Court $court): array => [
                'ulid' => $court->ulid,
                'name' => $court->name,
            ])->values()->all(),
            'slots' => $slots,
        ]);
    }

    public function store(CreateOtsBookingRequest $request, CreateOtsBooking $createOtsBooking, VenueContext $venueContext): RedirectResponse
    {
        $venue = $venueContext->venue();
        abort_if($venue === null, 403);
        Gate::authorize('createOts', [Booking::class, $venue]);

        $court = Court::query()
            ->where('ulid', $request->validated('court_ulid'))
            ->firstOrFail();

        $startsAt = CarbonImmutable::parse($request->validated('starts_at'))->utc();
        $endsAt = CarbonImmutable::parse($request->validated('ends_at'))->utc();

        $booking = $createOtsBooking->execute(
            $request->user(),
            $venue,
            $court,
            $startsAt,
            $endsAt,
            $request->validated(),
        );

        return redirect()
            ->route('dashboard.check-in.index')
            ->with('success', "Booking OTS {$booking->code} berhasil dibuat.");
    }
}
