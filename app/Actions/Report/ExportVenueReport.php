<?php

namespace App\Actions\Report;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Auth\Access\AuthorizationException;

class ExportVenueReport
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Venue $venue, string $period = 'daily', ?string $date = null, ?string $month = null): string
    {
        $subscription = $venue->activeSubscription();

        if ($subscription === null || ! $subscription->can_export_reports) {
            throw new AuthorizationException('Paket langganan Anda belum mendukung ekspor laporan. Silakan upgrade ke paket Pro atau Enterprise.');
        }

        $report = app(GetVenueFinancialReport::class)->execute($venue, $period, $date, $month);

        $bookings = Booking::withoutGlobalScopes()
            ->with([
                'court' => fn ($q) => $q->withoutGlobalScopes(),
                'payments' => fn ($q) => $q->withoutGlobalScopes()->where('status', Payment::STATUS_APPROVED),
            ])
            ->where('venue_id', $venue->id)
            ->whereBetween('play_date', [$report['start_date'], $report['end_date']])
            ->orderBy('play_date')
            ->orderBy('starts_at')
            ->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, [
            'Kode Booking',
            'Tanggal Main',
            'Lapangan',
            'Nama Pembooking',
            'Sumber',
            'Status',
            'Total Tagihan (Rp)',
            'Pembayaran Disetujui (Rp)',
            'Selisih (Rp)',
        ]);

        foreach ($bookings as $booking) {
            $approvedTotal = (int) $booking->payments->sum('amount');
            fputcsv($handle, [
                $booking->code,
                $booking->play_date->toDateString(),
                $booking->court?->name ?? '-',
                $booking->customer_name,
                $booking->source,
                $booking->status,
                $booking->price_total,
                $approvedTotal,
                max(0, (int) $booking->price_total - $approvedTotal),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        $this->recordAuditLog->execute(
            action: 'report.exported',
            actor: $actor,
            venueId: $venue->id,
            newValues: [
                'period' => $report['period'],
                'start_date' => $report['start_date'],
                'end_date' => $report['end_date'],
            ],
        );

        return $csv;
    }
}
