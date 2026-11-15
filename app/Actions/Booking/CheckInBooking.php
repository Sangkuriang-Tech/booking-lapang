<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Payment;
use App\Models\Scopes\VenueScope;
use App\Models\User;
use App\Models\VenueUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInBooking
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * Validate a scanned QR token without mutating state so staff can visually verify details.
     */
    public function lookupByToken(int $venueId, string $rawToken): Booking
    {
        $cleanToken = trim($rawToken);
        if ($cleanToken === '') {
            throw ValidationException::withMessages([
                'token' => ['Token QR wajib diisi.'],
            ]);
        }

        $hash = hash('sha256', $cleanToken);

        $booking = Booking::query()
            ->withoutGlobalScopes()
            ->where('qr_token_hash', $hash)
            ->with([
                'court' => static fn ($q) => $q->withoutGlobalScope(VenueScope::class)->select('id', 'ulid', 'venue_id', 'name'),
                'venue:id,ulid,name,timezone',
            ])
            ->first();

        $this->assertQrValid($booking, $venueId);

        return $booking;
    }

    public function execute(
        User $actor,
        int $venueId,
        string $rawToken,
        ?string $settlementMethod = null,
        ?int $settlementAmount = null,
        ?string $note = null,
    ): Booking {
        $cleanToken = trim($rawToken);
        if ($cleanToken === '') {
            throw ValidationException::withMessages([
                'token' => ['Token QR wajib diisi.'],
            ]);
        }

        $hash = hash('sha256', $cleanToken);
        $actorKind = $this->resolveActorKind($actor, $venueId);

        return DB::transaction(function () use (
            $actor,
            $actorKind,
            $venueId,
            $hash,
            $settlementMethod,
            $settlementAmount,
            $note,
        ): Booking {
            $locked = Booking::query()
                ->withoutGlobalScopes()
                ->where('qr_token_hash', $hash)
                ->lockForUpdate()
                ->first();

            $this->assertQrValid($locked, $venueId);

            $now = now();
            $remaining = max(0, $locked->price_total - $locked->paid_total);
            $paidTotal = $locked->paid_total;

            if ($remaining > 0) {
                $allowedMethods = [Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_QRIS];
                if ($settlementMethod === null || ! in_array($settlementMethod, $allowedMethods, true)) {
                    throw ValidationException::withMessages([
                        'settlement_method' => ['Pilih metode pelunasan (tunai, transfer, atau QRIS) sebelum check-in.'],
                    ]);
                }

                if ($settlementAmount !== $remaining) {
                    throw ValidationException::withMessages([
                        'settlement_amount' => ['Nominal pelunasan harus sesuai sisa tagihan (Rp'.number_format($remaining, 0, ',', '.').').'],
                    ]);
                }

                $settlement = Payment::query()->create([
                    'booking_id' => $locked->id,
                    'venue_id' => $locked->venue_id,
                    'kind' => Payment::KIND_SETTLEMENT,
                    'method' => $settlementMethod,
                    'amount' => $remaining,
                    'status' => Payment::STATUS_APPROVED,
                    'recorded_by' => $actor->id,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $now,
                    'note' => $note !== null && trim($note) !== '' ? trim($note) : 'Pelunasan di tempat saat check-in',
                ]);

                $paidTotal += $remaining;

                $this->recordAuditLog->execute(
                    action: 'payment.settlement_recorded',
                    actor: $actor,
                    venueId: $locked->venue_id,
                    subject: $settlement,
                    newValues: [
                        'booking_ulid' => $locked->ulid,
                        'method' => $settlementMethod,
                        'amount' => $remaining,
                    ],
                );
            }

            $locked->update([
                'status' => Booking::STATUS_CHECKED_IN,
                'paid_total' => $paidTotal,
                'qr_used_at' => $now,
                'checked_in_at' => $now,
            ]);

            BookingStatusLog::query()->create([
                'booking_id' => $locked->id,
                'from_status' => Booking::STATUS_CONFIRMED,
                'to_status' => Booking::STATUS_CHECKED_IN,
                'actor_id' => $actor->id,
                'actor_kind' => $actorKind,
                'reason' => null,
                'created_at' => $now,
            ]);

            $this->recordAuditLog->execute(
                action: 'booking.checked_in',
                actor: $actor,
                venueId: $locked->venue_id,
                subject: $locked,
                newValues: [
                    'booking_ulid' => $locked->ulid,
                    'settlement_amount' => $remaining,
                ],
            );

            return $locked->fresh();
        });
    }

    private function assertQrValid(?Booking $booking, int $venueId): void
    {
        if ($booking === null || $booking->venue_id !== $venueId) {
            throw ValidationException::withMessages([
                'token' => ['Token QR tidak valid untuk venue ini.'],
            ]);
        }

        if ($booking->qr_revoked_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['Token QR telah dicabut karena booking dibatalkan.'],
            ]);
        }

        if ($booking->qr_used_at !== null) {
            throw ValidationException::withMessages([
                'token' => ['Token QR sudah pernah digunakan untuk check-in.'],
            ]);
        }

        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw ValidationException::withMessages([
                'token' => ['Status booking tidak valid untuk check-in.'],
            ]);
        }

        $now = now();
        if (
            $booking->qr_valid_from === null
            || $booking->qr_valid_until === null
            || ! $now->betweenIncluded($booking->qr_valid_from, $booking->qr_valid_until)
        ) {
            throw ValidationException::withMessages([
                'token' => ['Token QR hanya berlaku mulai 60 menit sebelum jadwal hingga jam main selesai.'],
            ]);
        }
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
