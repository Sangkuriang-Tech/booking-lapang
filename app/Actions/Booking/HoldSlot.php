<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HoldSlot
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $customer, Court $court, CarbonInterface $startsAt, CarbonInterface $endsAt, array $data): Booking
    {
        $venue = Venue::query()->findOrFail($court->venue_id);

        if (! $venue->allowsOnlineBooking()) {
            throw ValidationException::withMessages([
                'venue_id' => ['Venue tidak sedang menerima booking online saat ini.'],
            ]);
        }

        try {
            return DB::transaction(function () use ($customer, $court, $venue, $startsAt, $endsAt, $data): Booking {
                // Lock the court row to serialize concurrent booking and schedule block writes.
                Court::withoutGlobalScopes()
                    ->whereKey($court->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $priceTotal = (int) ($data['price_total'] ?? 0);
                $paymentMode = $data['payment_mode'] ?? Booking::PAYMENT_MODE_DP;
                $dpRequired = $paymentMode === Booking::PAYMENT_MODE_FULL
                    ? $priceTotal
                    : (int) ceil($priceTotal * ($venue->dp_percent / 100));

                $playDate = $startsAt->toImmutable()->setTimezone($venue->timezone)->toDateString();

                $booking = Booking::query()->create([
                    'code' => Str::upper(Str::random(8)),
                    'venue_id' => $venue->id,
                    'court_id' => $court->id,
                    'customer_id' => $customer->id,
                    'customer_name' => $data['customer_name'] ?? $customer->name,
                    'customer_email' => $data['customer_email'] ?? $customer->email,
                    'customer_phone' => $data['customer_phone'] ?? $customer->phone,
                    'source' => Booking::SOURCE_ONLINE,
                    'status' => Booking::STATUS_HELD,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'play_date' => $playDate,
                    'price_total' => $priceTotal,
                    'payment_mode' => $paymentMode,
                    'dp_required' => $dpRequired,
                    'paid_total' => 0,
                    'hold_expires_at' => now()->addMinutes($venue->hold_minutes),
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'created_by' => $customer->id,
                ]);

                BookingStatusLog::query()->create([
                    'booking_id' => $booking->id,
                    'from_status' => null,
                    'to_status' => Booking::STATUS_HELD,
                    'actor_id' => $customer->id,
                    'actor_kind' => 'customer',
                    'created_at' => now(),
                ]);

                return $booking;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages([
                    'slot' => ['Slot baru saja diambil orang lain atau diblokir.'],
                ]);
            }

            throw $exception;
        }
    }
}
