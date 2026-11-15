<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\BookingStatusLog;
use App\Models\Court;
use App\Models\User;
use App\Models\Venue;
use App\Support\PhoneNormalizer;
use App\Support\SlotPricing;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class HoldSlot
{
    public const MAX_ACTIVE_HOLDS_PER_USER = 2;

    public const MAX_ACTIVE_HOLDS_PER_SUBNET_UNVERIFIED = 3;

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $customer, Court $court, CarbonInterface $startsAt, CarbonInterface $endsAt, array $data = []): Booking
    {
        if (! $customer->is_active || ! $customer->isCustomer()) {
            throw ValidationException::withMessages([
                'customer' => ['Hanya akun pembooking aktif yang dapat melakukan booking online.'],
            ]);
        }

        $idempotencyKey = isset($data['idempotency_key']) && is_string($data['idempotency_key'])
            ? trim($data['idempotency_key'])
            : null;
        if ($idempotencyKey === '') {
            $idempotencyKey = null;
        }

        if ($idempotencyKey !== null) {
            $existing = Booking::query()
                ->withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $venue = Venue::query()->findOrFail($court->venue_id);

        if (! $venue->allowsOnlineBooking()) {
            throw ValidationException::withMessages([
                'venue_id' => ['Venue tidak sedang menerima booking online saat ini.'],
            ]);
        }

        if (! $court->is_active) {
            throw ValidationException::withMessages([
                'court_id' => ['Lapangan sedang tidak aktif.'],
            ]);
        }

        if ($startsAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages([
                'slot' => ['Tidak dapat memesan slot yang sudah lewat.'],
            ]);
        }

        // Always compute pricing and operating-hour validity on the server (Threat T4).
        $pricing = SlotPricing::resolveForInterval($venue, $court, $startsAt, $endsAt);

        $this->enforceAntiHoarding($customer, $data['ip'] ?? null);

        $rawPhone = $data['customer_phone'] ?? $customer->phone;
        $normalizedPhone = null;
        if (is_string($rawPhone) && trim($rawPhone) !== '') {
            try {
                $normalizedPhone = PhoneNormalizer::normalize($rawPhone);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'customer_phone' => [$e->getMessage()],
                ]);
            }
        }

        $paymentMode = ($data['payment_mode'] ?? Booking::PAYMENT_MODE_DP) === Booking::PAYMENT_MODE_FULL
            ? Booking::PAYMENT_MODE_FULL
            : Booking::PAYMENT_MODE_DP;

        $priceTotal = $pricing['price_total'];
        $dpRequired = $paymentMode === Booking::PAYMENT_MODE_FULL
            ? $priceTotal
            : (int) ceil($priceTotal * ($venue->dp_percent / 100));

        $subnetKey = $this->resolveSubnetKey($data['ip'] ?? null);

        try {
            $booking = DB::transaction(function () use (
                $customer,
                $court,
                $venue,
                $startsAt,
                $endsAt,
                $pricing,
                $priceTotal,
                $paymentMode,
                $dpRequired,
                $normalizedPhone,
                $idempotencyKey,
                $data,
            ): Booking {
                // Lock the court row to serialize concurrent booking and schedule block writes.
                Court::withoutGlobalScopes()
                    ->whereKey($court->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $booking = Booking::query()->create([
                    'code' => Str::upper(Str::random(8)),
                    'venue_id' => $venue->id,
                    'court_id' => $court->id,
                    'customer_id' => $customer->id,
                    'customer_name' => $data['customer_name'] ?? $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $normalizedPhone,
                    'source' => Booking::SOURCE_ONLINE,
                    'status' => Booking::STATUS_HELD,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'play_date' => $pricing['play_date'],
                    'price_total' => $priceTotal,
                    'payment_mode' => $paymentMode,
                    'dp_required' => $dpRequired,
                    'paid_total' => 0,
                    'hold_expires_at' => now()->addMinutes($venue->hold_minutes),
                    'idempotency_key' => $idempotencyKey,
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

            if ($subnetKey !== null) {
                $this->recordSubnetHold($subnetKey, $booking->id, $venue->hold_minutes);
            }

            return $booking;
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23P01') {
                throw ValidationException::withMessages([
                    'slot' => ['Slot baru saja diambil orang lain atau diblokir.'],
                ]);
            }

            throw $exception;
        }
    }

    private function enforceAntiHoarding(User $customer, mixed $explicitIp = null): void
    {
        $activeUserHolds = Booking::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('status', Booking::STATUS_HELD)
            ->where('hold_expires_at', '>', now())
            ->count();

        if ($activeUserHolds >= self::MAX_ACTIVE_HOLDS_PER_USER) {
            throw ValidationException::withMessages([
                'slot' => ['Anda sudah memiliki terlalu banyak pesanan yang sedang ditahan. Selesaikan atau batalkan pesanan sebelumnya terlebih dahulu.'],
            ]);
        }

        $hasCompletedBooking = Booking::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->exists();

        if ($hasCompletedBooking) {
            return;
        }

        $subnetKey = $this->resolveSubnetKey($explicitIp);
        if ($subnetKey === null) {
            return;
        }

        /** @var list<int> $cachedIds */
        $cachedIds = Cache::get($this->subnetCacheKey($subnetKey), []);
        if ($cachedIds === []) {
            return;
        }

        $activeSubnetHolds = Booking::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $cachedIds)
            ->where('status', Booking::STATUS_HELD)
            ->where('hold_expires_at', '>', now())
            ->count();

        if ($activeSubnetHolds >= self::MAX_ACTIVE_HOLDS_PER_SUBNET_UNVERIFIED) {
            throw ValidationException::withMessages([
                'slot' => ['Terlalu banyak penahanan slot aktif dari jaringan Anda. Selesaikan pembayaran yang ada terlebih dahulu.'],
            ]);
        }
    }

    private function recordSubnetHold(string $subnetKey, int $bookingId, int $holdMinutes): void
    {
        $cacheKey = $this->subnetCacheKey($subnetKey);

        /** @var list<int> $existingIds */
        $existingIds = Cache::get($cacheKey, []);
        $existingIds[] = $bookingId;
        $uniqueIds = array_values(array_unique(array_slice($existingIds, -20)));

        Cache::put($cacheKey, $uniqueIds, now()->addMinutes($holdMinutes + 10));
    }

    private function resolveSubnetKey(mixed $explicitIp): ?string
    {
        $ip = is_string($explicitIp) && $explicitIp !== ''
            ? $explicitIp
            : (app()->bound('request') ? request()->ip() : null);

        if (! is_string($ip) || $ip === '') {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.0/24';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                return bin2hex(substr($packed, 0, 8)).'/64';
            }
        }

        return null;
    }

    private function subnetCacheKey(string $subnetKey): string
    {
        return 'hold_subnet:'.sha1($subnetKey);
    }
}
