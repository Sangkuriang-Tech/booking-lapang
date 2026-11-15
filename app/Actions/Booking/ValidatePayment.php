<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingReminder;
use App\Models\BookingStatusLog;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Notifications\BookingConfirmedNotification;
use App\Notifications\PaymentActionRequiredNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ValidatePayment
{
    public const DECISION_APPROVE = 'approve';

    public const DECISION_REUPLOAD = 'reupload';

    public const DECISION_REJECT = 'reject';

    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    public function execute(User $actor, Booking $booking, string $decision, ?string $reason = null): Booking
    {
        if (! in_array($decision, [self::DECISION_APPROVE, self::DECISION_REUPLOAD, self::DECISION_REJECT], true)) {
            throw ValidationException::withMessages([
                'decision' => ['Keputusan validasi tidak dikenali.'],
            ]);
        }

        $cleanReason = $reason !== null ? trim($reason) : '';
        if ($decision !== self::DECISION_APPROVE && $cleanReason === '') {
            throw ValidationException::withMessages([
                'reason' => ['Alasan wajib diisi saat meminta bukti baru atau menolak pembayaran.'],
            ]);
        }

        $actorKind = $this->resolveActorKind($actor, $booking->venue_id);

        $updated = DB::transaction(function () use ($actor, $actorKind, $booking, $decision, $cleanReason): Booking {
            $locked = Booking::query()
                ->withoutGlobalScopes()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($locked->status, [Booking::STATUS_AWAITING_VALIDATION, Booking::STATUS_AWAITING_REUPLOAD], true)) {
                throw ValidationException::withMessages([
                    'booking' => ['Status booking tidak berada dalam antrean validasi pembayaran.'],
                ]);
            }

            $venue = Venue::query()->findOrFail($locked->venue_id);
            $now = now();
            $fromStatus = $locked->status;

            $pendingPayment = Payment::query()
                ->withoutGlobalScopes()
                ->where('booking_id', $locked->id)
                ->where('status', Payment::STATUS_PENDING)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($decision === self::DECISION_APPROVE) {
                if ($pendingPayment === null) {
                    throw ValidationException::withMessages([
                        'payment' => ['Tidak ditemukan pembayaran berstatus menunggu pada booking ini.'],
                    ]);
                }

                $pendingPayment->update([
                    'status' => Payment::STATUS_APPROVED,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $now,
                    'reject_reason' => null,
                ]);

                $paidTotal = (int) Payment::query()
                    ->withoutGlobalScopes()
                    ->where('booking_id', $locked->id)
                    ->where('status', Payment::STATUS_APPROVED)
                    ->sum('amount');

                // Generate 32-byte CSPRNG url-safe token (Threat T7, OWASP A04).
                $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $tokenHash = hash('sha256', $rawToken);

                $locked->update([
                    'status' => Booking::STATUS_CONFIRMED,
                    'paid_total' => min($locked->price_total, $paidTotal),
                    'hold_expires_at' => null,
                    'confirmed_at' => $now,
                    'status_reason' => null,
                    'qr_token_hash' => $tokenHash,
                    'qr_token_enc' => $rawToken,
                    'qr_valid_from' => $locked->starts_at->subMinutes(60),
                    'qr_valid_until' => $locked->ends_at,
                    'qr_used_at' => null,
                    'qr_revoked_at' => null,
                ]);

                $this->scheduleReminders($locked);

                BookingStatusLog::query()->create([
                    'booking_id' => $locked->id,
                    'from_status' => $fromStatus,
                    'to_status' => Booking::STATUS_CONFIRMED,
                    'actor_id' => $actor->id,
                    'actor_kind' => $actorKind,
                    'reason' => null,
                    'created_at' => $now,
                ]);

                $this->recordAuditLog->execute(
                    action: 'payment.approved',
                    actor: $actor,
                    venueId: $locked->venue_id,
                    subject: $pendingPayment,
                    oldValues: ['status' => Payment::STATUS_PENDING],
                    newValues: [
                        'status' => Payment::STATUS_APPROVED,
                        'booking_ulid' => $locked->ulid,
                        'amount' => $pendingPayment->amount,
                    ],
                );

                return $locked->fresh();
            }

            if ($pendingPayment !== null) {
                $pendingPayment->update([
                    'status' => Payment::STATUS_REJECTED,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $now,
                    'reject_reason' => $cleanReason,
                ]);
            }

            if ($decision === self::DECISION_REUPLOAD) {
                $reuploadMinutes = $venue->reupload_minutes > 0 ? $venue->reupload_minutes : 60;

                $locked->update([
                    'status' => Booking::STATUS_AWAITING_REUPLOAD,
                    'hold_expires_at' => $now->addMinutes($reuploadMinutes),
                    'status_reason' => $cleanReason,
                ]);

                BookingStatusLog::query()->create([
                    'booking_id' => $locked->id,
                    'from_status' => $fromStatus,
                    'to_status' => Booking::STATUS_AWAITING_REUPLOAD,
                    'actor_id' => $actor->id,
                    'actor_kind' => $actorKind,
                    'reason' => $cleanReason,
                    'created_at' => $now,
                ]);

                $this->recordAuditLog->execute(
                    action: 'payment.reupload_requested',
                    actor: $actor,
                    venueId: $locked->venue_id,
                    subject: $pendingPayment ?? $locked,
                    newValues: [
                        'booking_ulid' => $locked->ulid,
                        'reason' => $cleanReason,
                    ],
                );

                return $locked->fresh();
            }

            // Reject permanently and release the slot immediately.
            $locked->update([
                'status' => Booking::STATUS_REJECTED,
                'hold_expires_at' => null,
                'status_reason' => $cleanReason,
            ]);

            BookingStatusLog::query()->create([
                'booking_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => Booking::STATUS_REJECTED,
                'actor_id' => $actor->id,
                'actor_kind' => $actorKind,
                'reason' => $cleanReason,
                'created_at' => $now,
            ]);

            $this->recordAuditLog->execute(
                action: 'payment.rejected',
                actor: $actor,
                venueId: $locked->venue_id,
                subject: $pendingPayment ?? $locked,
                newValues: [
                    'booking_ulid' => $locked->ulid,
                    'reason' => $cleanReason,
                ],
            );

            return $locked->fresh();
        });

        $updated->loadMissing('customer');
        if ($updated->customer !== null) {
            if ($decision === self::DECISION_APPROVE) {
                $updated->customer->notify(new BookingConfirmedNotification($updated->id));
            } elseif ($decision === self::DECISION_REUPLOAD) {
                $updated->customer->notify(new PaymentActionRequiredNotification($updated->id, 'reupload', $cleanReason));
            } else {
                $updated->customer->notify(new PaymentActionRequiredNotification($updated->id, 'rejected', $cleanReason));
            }
        }

        return $updated;
    }

    private function scheduleReminders(Booking $booking): void
    {
        $now = now();
        $reminders = [
            BookingReminder::KIND_H_MINUS_1D => $booking->starts_at->subDay(),
            BookingReminder::KIND_H_MINUS_5H => $booking->starts_at->subHours(5),
        ];

        foreach ($reminders as $kind => $sendAt) {
            if ($sendAt->lessThanOrEqualTo($now)) {
                continue;
            }

            BookingReminder::query()->updateOrCreate(
                [
                    'booking_id' => $booking->id,
                    'kind' => $kind,
                ],
                [
                    'send_at' => $sendAt,
                    'sent_at' => null,
                ],
            );
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
