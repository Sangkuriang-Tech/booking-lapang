<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use Illuminate\Support\Facades\DB;

class TransitionEndedBookings
{
    /**
     * Automatically complete checked_in bookings and mark confirmed bookings without check-in as no_show.
     *
     * @return array{completed: int, no_show: int}
     */
    public function execute(): array
    {
        $now = now();
        $completedCount = 0;
        $noShowCount = 0;

        $endedIds = Booking::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [Booking::STATUS_CHECKED_IN, Booking::STATUS_CONFIRMED])
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');

        foreach ($endedIds as $bookingId) {
            DB::transaction(function () use ($bookingId, $now, &$completedCount, &$noShowCount): void {
                $booking = Booking::query()
                    ->withoutGlobalScopes()
                    ->whereKey($bookingId)
                    ->lockForUpdate()
                    ->first();

                if ($booking === null || $booking->ends_at->greaterThan($now)) {
                    return;
                }

                if ($booking->status === Booking::STATUS_CHECKED_IN) {
                    $booking->update([
                        'status' => Booking::STATUS_COMPLETED,
                        'completed_at' => $now,
                    ]);

                    BookingStatusLog::query()->create([
                        'booking_id' => $booking->id,
                        'from_status' => Booking::STATUS_CHECKED_IN,
                        'to_status' => Booking::STATUS_COMPLETED,
                        'actor_id' => null,
                        'actor_kind' => 'system',
                        'reason' => 'Booking completed automatically after ends_at.',
                        'created_at' => $now,
                    ]);

                    $completedCount++;
                } elseif ($booking->status === Booking::STATUS_CONFIRMED) {
                    $booking->update([
                        'status' => Booking::STATUS_NO_SHOW,
                        'status_reason' => 'Pelanggan tidak melakukan check-in hingga waktu main berakhir.',
                    ]);

                    BookingStatusLog::query()->create([
                        'booking_id' => $booking->id,
                        'from_status' => Booking::STATUS_CONFIRMED,
                        'to_status' => Booking::STATUS_NO_SHOW,
                        'actor_id' => null,
                        'actor_kind' => 'system',
                        'reason' => 'Marked as no_show automatically after ends_at.',
                        'created_at' => $now,
                    ]);

                    $noShowCount++;
                }
            });
        }

        return [
            'completed' => $completedCount,
            'no_show' => $noShowCount,
        ];
    }
}
