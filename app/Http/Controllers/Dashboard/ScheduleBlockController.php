<?php

namespace App\Http\Controllers\Dashboard;

use App\Actions\Schedule\CreateScheduleBlock;
use App\Actions\Schedule\DeleteScheduleBlock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreScheduleBlockRequest;
use App\Models\Booking;
use App\Models\Court;
use App\Models\ScheduleBlock;
use App\Models\Scopes\VenueScope;
use App\Support\VenueContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleBlockController extends Controller
{
    public function index(Request $request, VenueContext $venueContext): Response
    {
        Gate::authorize('viewAny', ScheduleBlock::class);

        $venue = $venueContext->venue();
        abort_if($venue === null, 403);
        $timezone = $venue->timezone ?: 'Asia/Jakarta';

        $date = $request->string('date')->toString();
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = now()->setTimezone($timezone)->toDateString();
        }

        $dayStartUtc = CarbonImmutable::parse($date.' 00:00:00', $timezone)->utc();
        $dayEndUtc = $dayStartUtc->addDay();

        $courts = Court::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'ulid', 'name']);

        $blocks = ScheduleBlock::query()
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
            ->with([
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
            ])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (ScheduleBlock $block): array => [
                'id' => $block->id,
                'court_ulid' => $block->court->ulid,
                'court_name' => $block->court->name,
                'starts_at' => $block->starts_at->toIso8601String(),
                'ends_at' => $block->ends_at->toIso8601String(),
                'reason' => $block->reason,
            ])
            ->values()
            ->all();

        $bookings = Booking::query()
            ->whereIn('status', Booking::LOCKED_STATUSES)
            ->where('starts_at', '<', $dayEndUtc)
            ->where('ends_at', '>', $dayStartUtc)
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
                'court_name' => $booking->court->name,
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('Dashboard/Schedule/Index', [
            'selectedDate' => $date,
            'timezone' => $timezone,
            'courts' => $courts->map(fn (Court $court): array => [
                'ulid' => $court->ulid,
                'name' => $court->name,
            ])->values()->all(),
            'blocks' => $blocks,
            'bookings' => $bookings,
        ]);
    }

    public function store(StoreScheduleBlockRequest $request, CreateScheduleBlock $createScheduleBlock): RedirectResponse
    {
        Gate::authorize('create', ScheduleBlock::class);

        $court = Court::query()
            ->where('ulid', $request->validated('court_ulid'))
            ->firstOrFail();

        $startsAt = CarbonImmutable::parse($request->validated('starts_at'))->utc();
        $endsAt = CarbonImmutable::parse($request->validated('ends_at'))->utc();

        $createScheduleBlock->execute(
            $request->user(),
            $court,
            $startsAt,
            $endsAt,
            $request->validated('reason'),
        );

        return redirect()
            ->back()
            ->with('success', 'Blokir jadwal berhasil dibuat.');
    }

    public function destroy(Request $request, int $id, DeleteScheduleBlock $deleteScheduleBlock): RedirectResponse
    {
        $block = ScheduleBlock::query()->findOrFail($id);
        Gate::authorize('delete', $block);

        $deleteScheduleBlock->execute($request->user(), $block);

        return redirect()
            ->back()
            ->with('success', 'Blokir jadwal berhasil dihapus.');
    }
}
