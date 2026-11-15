<?php

namespace App\Support;

use App\Models\Court;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\Scopes\VenueScope;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SlotPricing
{
    /**
     * Resolve play_date, operating hour window, and hourly price breakdown for a booking interval.
     *
     * @return array{
     *     play_date: string,
     *     start_min: int,
     *     end_min: int,
     *     price_total: int,
     *     hourly_prices: list<array{start_min: int, end_min: int, price_per_hour: int}>
     * }
     */
    public static function resolveForInterval(
        Venue $venue,
        Court $court,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): array {
        $timezone = $venue->timezone ?: 'Asia/Jakarta';
        $localStart = CarbonImmutable::instance($startsAt)->setTimezone($timezone);
        $localEnd = CarbonImmutable::instance($endsAt)->setTimezone($timezone);

        if ($localEnd->lessThanOrEqualTo($localStart)) {
            throw ValidationException::withMessages([
                'slot' => ['Jam selesai harus lebih besar dari jam mulai.'],
            ]);
        }

        $durationMinutes = (int) $localStart->diffInMinutes($localEnd);
        if ($durationMinutes < 60 || $durationMinutes % 60 !== 0) {
            throw ValidationException::withMessages([
                'slot' => ['Durasi booking harus kelipatan 1 jam.'],
            ]);
        }

        if ($localStart->second !== 0) {
            throw ValidationException::withMessages([
                'slot' => ['Jam mulai harus tepat pada menit ke-00.'],
            ]);
        }

        $playDate = $localStart->toDateString();
        $startMin = ($localStart->hour * 60) + $localStart->minute;
        $endMin = $startMin + $durationMinutes;
        $weekday = $localStart->dayOfWeek;

        $operatingHour = OperatingHour::query()
            ->withoutGlobalScope(VenueScope::class)
            ->where('venue_id', $venue->id)
            ->where('weekday', $weekday)
            ->first();

        $fitsCurrentDay = $operatingHour !== null
            && ! $operatingHour->is_closed
            && $startMin >= $operatingHour->opens_min
            && $endMin <= $operatingHour->closes_min;

        // Handle post-midnight slots (e.g. 00:00-01:00) belonging to previous day's closes_min > 1440.
        if (! $fitsCurrentDay && $localStart->hour < 6) {
            $prevDate = $localStart->subDay();
            $prevOperatingHour = OperatingHour::query()
                ->withoutGlobalScope(VenueScope::class)
                ->where('venue_id', $venue->id)
                ->where('weekday', $prevDate->dayOfWeek)
                ->first();

            $spillStartMin = 1440 + $startMin;
            $spillEndMin = $spillStartMin + $durationMinutes;

            if (
                $prevOperatingHour !== null
                && ! $prevOperatingHour->is_closed
                && $spillStartMin >= $prevOperatingHour->opens_min
                && $spillEndMin <= $prevOperatingHour->closes_min
            ) {
                $playDate = $prevDate->toDateString();
                $startMin = $spillStartMin;
                $endMin = $spillEndMin;
                $weekday = $prevDate->dayOfWeek;
                $fitsCurrentDay = true;
            }
        }

        if (! $fitsCurrentDay) {
            throw ValidationException::withMessages([
                'slot' => ['Jam yang dipilih berada di luar jam operasional venue.'],
            ]);
        }

        $dayType = self::dayTypeForWeekday($weekday);

        /** @var Collection<int, PriceRule> $rules */
        $rules = PriceRule::query()
            ->where('court_id', $court->id)
            ->where('day_type', $dayType)
            ->orderBy('start_min')
            ->get();

        $hourlyPrices = [];
        $priceTotal = 0;

        for ($cursor = $startMin; $cursor < $endMin; $cursor += 60) {
            $slotEnd = $cursor + 60;
            $rule = self::findRuleForHour($rules, $cursor, $slotEnd);

            if ($rule === null) {
                throw ValidationException::withMessages([
                    'slot' => ['Tarif belum tersedia untuk sebagian jam yang dipilih.'],
                ]);
            }

            $hourlyPrices[] = [
                'start_min' => $cursor,
                'end_min' => $slotEnd,
                'price_per_hour' => $rule->price_per_hour,
            ];
            $priceTotal += $rule->price_per_hour;
        }

        return [
            'play_date' => $playDate,
            'start_min' => $startMin,
            'end_min' => $endMin,
            'price_total' => $priceTotal,
            'hourly_prices' => $hourlyPrices,
        ];
    }

    public static function dayTypeForWeekday(int $weekday): string
    {
        return in_array($weekday, [0, 6], true)
            ? PriceRule::DAY_TYPE_WEEKEND
            : PriceRule::DAY_TYPE_WEEKDAY;
    }

    /**
     * @param  Collection<int, PriceRule>  $rules
     */
    public static function findRuleForHour(Collection $rules, int $startMin, int $endMin): ?PriceRule
    {
        return $rules->first(
            fn (PriceRule $rule): bool => $rule->start_min <= $startMin && $rule->end_min >= $endMin
        );
    }

    public static function formatMinuteLabel(int $minute): string
    {
        $normalized = $minute % 1440;
        $hour = intdiv($normalized, 60);
        $min = $normalized % 60;

        return sprintf('%02d.%02d', $hour, $min);
    }
}
