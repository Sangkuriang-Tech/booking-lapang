<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use Illuminate\Support\Facades\DB;

class ExpireStaleBookings
{
    /**
     * Transition expired held and awaiting_reupload bookings to terminal statuses.
     *
     * @return array{expired: int, rejected: int}
     */
    public function execute(): array
    {
        $now = now();
        $expiredCount = 0;
        $rejectedCount = 0;

        $staleIds = Booking::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [Booking::STATUS_HELD, Booking::STATUS_AWAITING_REUPLOAD])
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');

        foreach ($staleIds as $bookingId) {
            DB::transaction(function () use ($bookingId, $now, &$expiredCount, &$rejectedCount): void {
                $booking = Booking::query()
                    ->withoutGlobalScopes()
                    ->whereKey($bookingId)
                    ->lockForUpdate()
                    ->first();

                if ($booking === null || $booking->hold_expires_at === null || $booking->hold_expires_at->greaterThan($now)) {
                    return;
                }

                if ($booking->status === Booking::STATUS_HELD) {
                    $booking->update([
                        'status' => Booking::STATUS_EXPIRED,
                        'hold_expires_at' => null,
                        'status_reason' => 'Batas waktu penahanan slot berakhir.',
                    ]);

                    BookingStatusLog::query()->create([
                        'booking_id' => $booking->id,
                        'from_status' => Booking::STATUS_HELD,
                        'to_status' => Booking::STATUS_EXPIRED,
                        'actor_id' => null,
                        'actor_kind' => 'system',
                        'reason' => 'Hold expired automatically.',
                        'created_at' => $now,
                    ]);

                    $expiredCount++;
                } elseif ($booking->status === Booking::STATUS_AWAITING_REUPLOAD) {
                    $booking->update([
                        'status' => Booking::STATUS_REJECTED,
                        'hold_expires_at' => null,
                        'status_reason' => 'Batas waktu unggah ulang bukti pembayaran berakhir.',
                    ]);

                    BookingStatusLog::query()->create([
                        'booking_id' => $booking->id,
                        'from_status' => Booking::STATUS_AWAITING_REUPLOAD,
                        'to_status' => Booking::STATUS_REJECTED,
                        'actor_id' => null,
                        'actor_kind' => 'system',
                        'reason' => 'Reupload window expired automatically.',
                        'created_at' => $now,
                    ]);

                    $rejectedCount++;
                }
            });
        }

        return [
            'expired' => $expiredCount,
            'rejected' => $rejectedCount,
        ];
    }
}
