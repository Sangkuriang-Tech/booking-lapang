<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\User;
use App\Models\VenueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteBooking
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Booking $booking): Booking
    {
        $actorKind = $this->resolveActorKind($actor, $booking->venue_id);

        return DB::transaction(function () use ($actor, $actorKind, $booking): Booking {
            $locked = Booking::query()
                ->withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== Booking::STATUS_CHECKED_IN) {
                throw ValidationException::withMessages([
                    'booking' => ['Hanya booking berstatus check-in yang dapat ditandai selesai.'],
                ]);
            }

            $now = now();

            $locked->update([
                'status' => Booking::STATUS_COMPLETED,
                'completed_at' => $now,
            ]);

            BookingStatusLog::query()->create([
                'booking_id' => $locked->id,
                'from_status' => Booking::STATUS_CHECKED_IN,
                'to_status' => Booking::STATUS_COMPLETED,
                'actor_id' => $actor->id,
                'actor_kind' => $actorKind,
                'reason' => null,
                'created_at' => $now,
            ]);

            $this->recordAuditLog->execute(
                action: 'booking.completed',
                actor: $actor,
                venueId: $locked->venue_id,
                subject: $locked,
                newValues: ['booking_ulid' => $locked->ulid],
            );

            return $locked->fresh();
        });
    }

    private function resolveActorKind(User $actor, int $venueId): string
    {
        if ($actor->isSuperadmin()) {
            return 'superadmin';
        }

        $membershipRole = VenueUser::query()
            ->where('venue_id', $venueId)
            ->where('user_id', $actor->id)
            ->value('role');

        return $membershipRole === VenueUser::ROLE_OWNER ? 'owner' : 'staff';
    }
}
