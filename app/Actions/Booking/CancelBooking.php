<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelBooking
{
    /** @var list<string> */
    private const CANCELLABLE_STATUSES = [
        Booking::STATUS_HELD,
        Booking::STATUS_AWAITING_VALIDATION,
        Booking::STATUS_AWAITING_REUPLOAD,
        Booking::STATUS_CONFIRMED,
    ];

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Booking $booking, ?string $reason = null): Booking
    {
        $venue = Venue::query()->findOrFail($booking->venue_id);

        if (! in_array($booking->status, self::CANCELLABLE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'booking' => ['Pesanan dengan status saat ini tidak dapat dibatalkan.'],
            ]);
        }

        if ($actor->isCustomer() && $booking->status === Booking::STATUS_CONFIRMED) {
            $deadline = $booking->starts_at->subHours($venue->cancel_min_hours);

            if (now()->greaterThan($deadline)) {
                throw ValidationException::withMessages([
                    'booking' => ["Pembatalan hanya dapat dilakukan minimal {$venue->cancel_min_hours} jam sebelum jadwal main."],
                ]);
            }
        }

        $actorKind = match (true) {
            $actor->isSuperadmin() => 'superadmin',
            $actor->isCustomer() => 'customer',
            default => 'staff',
        };

        $cancelled = DB::transaction(function () use ($actor, $actorKind, $booking, $reason): Booking {
            $locked = Booking::query()
                ->withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, self::CANCELLABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'booking' => ['Pesanan dengan status saat ini tidak dapat dibatalkan.'],
                ]);
            }

            $fromStatus = $locked->status;

            $locked->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'hold_expires_at' => null,
                'qr_revoked_at' => $locked->qr_token_hash !== null ? now() : $locked->qr_revoked_at,
                'status_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
            ]);

            BookingStatusLog::query()->create([
                'booking_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => Booking::STATUS_CANCELLED,
                'actor_id' => $actor->id,
                'actor_kind' => $actorKind,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });

        $this->recordAuditLog->execute(
            action: 'booking.cancelled',
            actor: $actor,
            venueId: $booking->venue_id,
            subject: $cancelled,
            newValues: [
                'booking_ulid' => $cancelled->ulid,
                'actor_kind' => $actorKind,
            ],
        );

        return $cancelled;
    }
}
