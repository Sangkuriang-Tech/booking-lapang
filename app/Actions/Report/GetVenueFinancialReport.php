<?php

namespace App\Actions\Report;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GetVenueFinancialReport
{
    /**
     * @return array{
     *     period: string,
     *     date: string,
     *     month: string,
     *     start_date: string,
     *     end_date: string,
     *     summary: array{
     *         total_revenue: int,
     *         dp_revenue: int,
     *         settlement_revenue: int,
     *         full_revenue: int,
     *         total_bookings: int,
     *         discrepancy_count: int
     *     },
     *     status_counts: array<string, int>,
     *     daily_breakdown: list<array{play_date: string, revenue: int, bookings_count: int}>,
     *     discrepancies: list<array{
     *         ulid: string,
     *         code: string,
     *         play_date: string,
     *         court_name: string,
     *         customer_name: string,
     *         status: string,
     *         total_amount: int,
     *         approved_payment_total: int
     *     }>,
     *     can_export_reports: bool
     * }
     */
    public function execute(Venue $venue, string $period = 'daily', ?string $date = null, ?string $month = null): array
    {
        $timezone = $venue->timezone ?: 'Asia/Jakarta';
        $nowInVenue = CarbonImmutable::now($timezone);

        $resolvedDate = $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            ? CarbonImmutable::createFromFormat('Y-m-d', $date, $timezone)->startOfDay()
            : $nowInVenue->startOfDay();

        $resolvedMonth = $month !== null && preg_match('/^\d{4}-\d{2}$/', $month)
            ? CarbonImmutable::createFromFormat('Y-m', $month, $timezone)->startOfMonth()
            : $nowInVenue->startOfMonth();

        if ($period === 'monthly') {
            $startDate = $resolvedMonth->startOfMonth()->toDateString();
            $endDate = $resolvedMonth->endOfMonth()->toDateString();
        } else {
            $period = 'daily';
            $startDate = $resolvedDate->toDateString();
            $endDate = $resolvedDate->toDateString();
        }

        $statusRows = Booking::withoutGlobalScopes()
            ->where('venue_id', $venue->id)
            ->whereBetween('play_date', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*)::int as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $allStatuses = [
            Booking::STATUS_HELD,
            Booking::STATUS_AWAITING_VALIDATION,
            Booking::STATUS_AWAITING_REUPLOAD,
            Booking::STATUS_CONFIRMED,
            Booking::STATUS_CHECKED_IN,
            Booking::STATUS_COMPLETED,
            Booking::STATUS_REJECTED,
            Booking::STATUS_EXPIRED,
            Booking::STATUS_CANCELLED,
            Booking::STATUS_NO_SHOW,
        ];

        $statusCounts = [];
        foreach ($allStatuses as $status) {
            $statusCounts[$status] = (int) ($statusRows[$status] ?? 0);
        }

        $revenueByKind = Payment::withoutGlobalScopes()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->where('bookings.venue_id', $venue->id)
            ->where('payments.venue_id', $venue->id)
            ->where('payments.status', Payment::STATUS_APPROVED)
            ->whereBetween('bookings.play_date', [$startDate, $endDate])
            ->select('payments.kind', DB::raw('COALESCE(SUM(payments.amount), 0)::bigint as total'))
            ->groupBy('payments.kind')
            ->pluck('total', 'kind')
            ->all();

        $dpRevenue = (int) ($revenueByKind[Payment::KIND_DP] ?? 0);
        $settlementRevenue = (int) ($revenueByKind[Payment::KIND_SETTLEMENT] ?? 0);
        $fullRevenue = (int) ($revenueByKind[Payment::KIND_FULL] ?? 0);
        $totalRevenue = $dpRevenue + $settlementRevenue + $fullRevenue;

        $dailyRows = Booking::withoutGlobalScopes()
            ->leftJoin('payments', function ($join): void {
                $join->on('payments.booking_id', '=', 'bookings.id')
                    ->where('payments.status', '=', Payment::STATUS_APPROVED);
            })
            ->where('bookings.venue_id', $venue->id)
            ->whereBetween('bookings.play_date', [$startDate, $endDate])
            ->select(
                'bookings.play_date',
                DB::raw('COALESCE(SUM(payments.amount), 0)::bigint as revenue'),
                DB::raw('COUNT(DISTINCT bookings.id)::int as bookings_count'),
            )
            ->groupBy('bookings.play_date')
            ->orderBy('bookings.play_date')
            ->get()
            ->map(fn ($row): array => [
                'play_date' => CarbonImmutable::parse($row->play_date)->toDateString(),
                'revenue' => (int) $row->revenue,
                'bookings_count' => (int) $row->bookings_count,
            ])
            ->values()
            ->all();

        // Threat T11: confirmed/checked_in/completed bookings without any approved payment recorded
        $discrepancies = Booking::withoutGlobalScopes()
            ->with(['court' => fn ($q) => $q->withoutGlobalScopes()])
            ->where('venue_id', $venue->id)
            ->whereBetween('play_date', [$startDate, $endDate])
            ->whereIn('status', [
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_CHECKED_IN,
                Booking::STATUS_COMPLETED,
            ])
            ->whereDoesntHave('payments', function ($query): void {
                $query->withoutGlobalScopes()
                    ->where('status', Payment::STATUS_APPROVED)
                    ->where('amount', '>', 0);
            })
            ->orderBy('play_date')
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $booking): array => [
                'ulid' => $booking->ulid,
                'code' => $booking->code,
                'play_date' => $booking->play_date->toDateString(),
                'court_name' => $booking->court?->name ?? '-',
                'customer_name' => $booking->customer_name,
                'status' => $booking->status,
                'total_amount' => (int) $booking->price_total,
                'approved_payment_total' => 0,
            ])
            ->values()
            ->all();

        $subscription = $venue->activeSubscription();
        $canExport = $subscription !== null && (bool) $subscription->can_export_reports;

        return [
            'period' => $period,
            'date' => $resolvedDate->toDateString(),
            'month' => $resolvedMonth->format('Y-m'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'dp_revenue' => $dpRevenue,
                'settlement_revenue' => $settlementRevenue,
                'full_revenue' => $fullRevenue,
                'total_bookings' => array_sum($statusCounts),
                'discrepancy_count' => count($discrepancies),
            ],
            'status_counts' => $statusCounts,
            'daily_breakdown' => $dailyRows,
            'discrepancies' => $discrepancies,
            'can_export_reports' => $canExport,
        ];
    }
}
