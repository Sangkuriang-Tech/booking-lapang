<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveVenueUser
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Venue $venue, User $targetUser): void
    {
        $membership = VenueUser::query()
            ->where('venue_id', $venue->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($membership === null) {
            return;
        }

        if ($membership->role === VenueUser::ROLE_OWNER) {
            $ownerCount = VenueUser::query()
                ->where('venue_id', $venue->id)
                ->where('role', VenueUser::ROLE_OWNER)
                ->count();

            if ($ownerCount <= 1) {
                throw ValidationException::withMessages([
                    'user_id' => ['Pemilik terakhir venue tidak boleh dihapus.'],
                ]);
            }
        }

        DB::transaction(function () use ($actor, $venue, $targetUser, $membership): void {
            VenueUser::query()
                ->where('venue_id', $venue->id)
                ->where('user_id', $targetUser->id)
                ->delete();

            $this->recordAuditLog->execute(
                action: 'venue_user.removed',
                actor: $actor,
                venueId: $venue->id,
                oldValues: ['user_id' => $targetUser->id, 'role' => $membership->role],
            );
        });
    }
}
