<?php

namespace App\Actions\Venue;

use App\Actions\Audit\RecordAuditLog;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateVenue
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, User $owner, array $data): Venue
    {
        $subscription = $owner->latestSubscription();

        if ($subscription === null || $subscription->isInactiveOn(timezone: $data['timezone'] ?? 'Asia/Jakarta')) {
            throw ValidationException::withMessages([
                'owner_id' => ['Pemilik belum memiliki langganan aktif.'],
            ]);
        }

        $activeVenuesCount = Venue::query()
            ->where('owner_id', $owner->id)
            ->count();

        if ($subscription->max_venues !== null && $activeVenuesCount >= $subscription->max_venues) {
            throw ValidationException::withMessages([
                'owner_id' => ['Kuota venue paket langganan sudah penuh. Silakan upgrade paket untuk menambah venue.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $owner, $data): Venue {
            $venue = Venue::query()->create([
                'owner_id' => $owner->id,
                'slug' => $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4)),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'city' => $data['city'],
                'city_slug' => $data['city_slug'] ?? Str::slug($data['city']),
                'address' => $data['address'],
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'contact_phone' => $data['contact_phone'] ?? null,
                'timezone' => $data['timezone'] ?? 'Asia/Jakarta',
                'hold_minutes' => $data['hold_minutes'] ?? 20,
                'dp_percent' => $data['dp_percent'] ?? 30,
                'cancel_min_hours' => $data['cancel_min_hours'] ?? 24,
                'reupload_minutes' => $data['reupload_minutes'] ?? 60,
                'is_published' => (bool) ($data['is_published'] ?? false),
            ]);

            VenueUser::query()->create([
                'venue_id' => $venue->id,
                'user_id' => $owner->id,
                'role' => VenueUser::ROLE_OWNER,
                'created_at' => now(),
            ]);

            $this->recordAuditLog->execute(
                action: 'venue.created',
                actor: $actor,
                venueId: $venue->id,
                subject: $venue,
                newValues: ['name' => $venue->name, 'owner_id' => $owner->id],
            );

            return $venue;
        });
    }
}
