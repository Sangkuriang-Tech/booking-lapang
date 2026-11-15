<?php

namespace App\Actions\Staff;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignVenueUser
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Venue $venue, User $targetUser, string $role): VenueUser
    {
        if (! in_array($role, [VenueUser::ROLE_OWNER, VenueUser::ROLE_STAFF], true)) {
            throw ValidationException::withMessages([
                'role' => ['Peran venue tidak valid.'],
            ]);
        }

        $subscription = $venue->activeSubscription();

        if ($subscription === null || $subscription->isInactiveOn(timezone: $venue->timezone)) {
            throw ValidationException::withMessages([
                'venue_id' => ['Langganan venue tidak aktif.'],
            ]);
        }

        $existingMembership = VenueUser::query()
            ->where('venue_id', $venue->id)
            ->where('user_id', $targetUser->id)
            ->first();

        if ($role === VenueUser::ROLE_STAFF && $subscription->max_staff_total !== null) {
            $alreadyCountedAsStaff = DB::table('venue_users')
                ->join('venues', 'venues.id', '=', 'venue_users.venue_id')
                ->where('venues.owner_id', $venue->owner_id)
                ->whereNull('venues.deleted_at')
                ->where('venue_users.role', VenueUser::ROLE_STAFF)
                ->where('venue_users.user_id', $targetUser->id)
                ->exists();

            if (! $alreadyCountedAsStaff) {
                $totalUniqueStaff = DB::table('venue_users')
                    ->join('venues', 'venues.id', '=', 'venue_users.venue_id')
                    ->where('venues.owner_id', $venue->owner_id)
                    ->whereNull('venues.deleted_at')
                    ->where('venue_users.role', VenueUser::ROLE_STAFF)
                    ->distinct()
                    ->count('venue_users.user_id');

                if ($totalUniqueStaff >= $subscription->max_staff_total) {
                    throw ValidationException::withMessages([
                        'user_id' => ['Kuota total karyawan pada paket langganan sudah penuh. Silakan upgrade paket untuk menambah karyawan.'],
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($actor, $venue, $targetUser, $role, $existingMembership): VenueUser {
            if ($existingMembership !== null) {
                if ($existingMembership->role === VenueUser::ROLE_OWNER && $role !== VenueUser::ROLE_OWNER) {
                    $ownerCount = VenueUser::query()
                        ->where('venue_id', $venue->id)
                        ->where('role', VenueUser::ROLE_OWNER)
                        ->count();

                    if ($ownerCount <= 1) {
                        throw ValidationException::withMessages([
                            'role' => ['Pemilik terakhir venue tidak boleh diturunkan perannya.'],
                        ]);
                    }
                }

                VenueUser::query()
                    ->where('venue_id', $venue->id)
                    ->where('user_id', $targetUser->id)
                    ->update(['role' => $role]);

                $membership = VenueUser::query()
                    ->where('venue_id', $venue->id)
                    ->where('user_id', $targetUser->id)
                    ->firstOrFail();
            } else {
                $membership = VenueUser::query()->create([
                    'venue_id' => $venue->id,
                    'user_id' => $targetUser->id,
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }

            $this->recordAuditLog->execute(
                action: 'venue_user.assigned',
                actor: $actor,
                venueId: $venue->id,
                newValues: ['user_id' => $targetUser->id, 'role' => $role],
            );

            return $membership;
        });
    }
}
