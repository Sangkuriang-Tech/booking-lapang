<?php

namespace App\Actions\Booking;

use App\Actions\Audit\RecordAuditLog;
use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\Payment;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueUser;
use App\Support\PhoneNormalizer;
use App\Support\SlotPricing;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CreateOtsBooking
{
    public function __construct(
        private readonly RecordAuditLog $recordAuditLog,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Venue $venue, Court $court, CarbonInterface $startsAt, CarbonInterface $endsAt, array $data): Booking
    {
        if (! $venue->allowsOtsBooking()) {
            throw ValidationException::withMessages([
                'venue' => ['Langganan venue sudah tidak aktif melewati masa tenggang H+7. Pembuatan booking OTS dikunci.'],
            ]);
        }

        if ($court->venue_id !== $venue->id || ! $court->is_active) {
            throw ValidationException::withMessages([
                'court_id' => ['Lapangan tidak valid atau sedang tidak aktif.'],
            ]);
        }

        $pricing = SlotPricing::resolveForInterval($venue, $court, $startsAt, $endsAt);

        $rawPhone = (string) ($data['customer_phone'] ?? '');
        try {
            $normalizedPhone = PhoneNormalizer::normalize($rawPhone);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'customer_phone' => [$e->getMessage()],
            ]);
        }

        $paymentMode = ($data['payment_mode'] ?? Booking::PAYMENT_MODE_FULL) === Booking::PAYMENT_MODE_DP
            ? Booking::PAYMENT_MODE_DP
            : Booking::PAYMENT_MODE_FULL;

        $paymentMethod = (string) ($data['payment_method'] ?? Payment::METHOD_CASH);
        if (! in_array($paymentMethod, [Payment::METHOD_CASH, Payment::METHOD_TRANSFER, Payment::METHOD_QRIS], true)) {
            throw ValidationException::withMessages([
                'payment_method' => ['Metode pembayaran OTS tidak valid.'],
            ]);
        }

        $priceTotal = $pricing['price_total'];
        $dpRequired = $paymentMode === Booking::PAYMENT_MODE_FULL
            ? $priceTotal
            : (int) ceil($priceTotal * ($venue->dp_percent / 100));

        if ($paymentMode === Booking::PAYMENT_MODE_DP && $dpRequired <= 0) {
            $paymentMode = Booking::PAYMENT_MODE_FULL;
            $dpRequired = $priceTotal;
        }

        $paidAmount = $paymentMode === Booking::PAYMENT_MODE_FULL ? $priceTotal : $dpRequired;
        $directCheckIn = (bool) ($data['direct_check_in'] ?? false);
        $actorKind = $this->resolveActorKind($actor, $venue->id);

        try {
            return DB::transaction(function () use (
                $actor,
                $actorKind,
                $venue,
                $court,
                $startsAt,
                $endsAt,
                $pricing,
                $priceTotal,
                $paymentMode,
                $dpRequired,
                $paidAmount,
                $paymentMethod,
                $normalizedPhone,
                $directCheckIn,
                $data,
            ): Booking {
                Court::withoutGlobalScopes()
                    ->whereKey($court->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $now = now();
                $rawToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $tokenHash = hash('sha256', $rawToken);
                $targetStatus = $directCheckIn ? Booking::STATUS_CHECKED_IN : Booking::STATUS_CONFIRMED;

                $booking = Booking::query()->create([
                    'code' => Str::upper(Str::random(8)),
                    'venue_id' => $venue->id,
                    'court_id' => $court->id,
                    'customer_id' => null,
                    'customer_name' => trim((string) $data['customer_name']),
                    'customer_email' => null,
                    'customer_phone' => $normalizedPhone,
                    'source' => Booking::SOURCE_OTS,
                    'status' => $targetStatus,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'play_date' => $pricing['play_date'],
                    'price_total' => $priceTotal,
                    'payment_mode' => $paymentMode,
                    'dp_required' => $dpRequired,
                    'paid_total' => $paidAmount,
                    'hold_expires_at' => null,
                    'qr_token_hash' => $tokenHash,
                    'qr_token_enc' => $rawToken,
                    'qr_valid_from' => $startsAt->subMinutes(60),
                    'qr_valid_until' => $endsAt,
                    'qr_used_at' => $directCheckIn ? $now : null,
                    'confirmed_at' => $now,
                    'checked_in_at' => $directCheckIn ? $now : null,
                    'created_by' => $actor->id,
                ]);

                Payment::query()->create([
                    'booking_id' => $booking->id,
                    'venue_id' => $venue->id,
                    'kind' => $paymentMode === Booking::PAYMENT_MODE_FULL ? Payment::KIND_FULL : Payment::KIND_DP,
                    'method' => $paymentMethod,
                    'amount' => $paidAmount,
                    'status' => Payment::STATUS_APPROVED,
                    'recorded_by' => $actor->id,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => $now,
                    'note' => isset($data['note']) && is_string($data['note']) && trim($data['note']) !== ''
                        ? trim($data['note'])
                        : 'Pembayaran booking OTS',
                ]);

                BookingStatusLog::query()->create([
                    'booking_id' => $booking->id,
                    'from_status' => null,
                    'to_status' => $targetStatus,
                    'actor_id' => $actor->id,
                    'actor_kind' => $actorKind,
                    'reason' => $directCheckIn ? 'Booking OTS langsung check-in.' : 'Booking OTS dibuat.',
                    'created_at' => $now,
                ]);

                $this->recordAuditLog->execute(
                    action: 'booking.ots_created',
                    actor: $actor,
                    venueId: $venue->id,
                    subject: $booking,
                    newValues: [
                        'booking_ulid' => $booking->ulid,
                        'status' => $targetStatus,
                        'paid_total' => $paidAmount,
                        'payment_method' => $paymentMethod,
                    ],
                );

                return $booking;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages([
                    'slot' => ['Slot baru saja diambil atau diblokir. Pilih jam lain.'],
                ]);
            }

            throw $exception;
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
