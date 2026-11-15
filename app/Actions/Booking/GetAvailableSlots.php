<?php

namespace App\Actions\Booking;

use App\Models\Booking;
use App\Models\Court;
use App\Models\OperatingHour;
use App\Models\PriceRule;
use App\Models\ScheduleBlock;
use App\Models\Scopes\VenueScope;
use App\Models\Venue;
use App\Support\SlotPricing;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class GetAvailableSlots
{
    /**
     * Calculate hourly availability and pricing for a court on a specific local play_date.
     *
     * @return list<array{
     *     start_min: int,
     *     end_min: int,
     *     label: string,
     *     starts_at: string,
     *     ends_at: string,
     *     price: int|null,
     *     is_available: bool,
     *     unavailable_reason: string|null
     * }>
     */
    public function execute(Venue $venue, Court $court, CarbonImmutable $playDate): array
    {
        if ($court->venue_id !== $venue->id || ! $court->is_active || ! $venue->allowsPublicListing()) {
            return [];
        }

        $timezone = $venue->timezone ?: 'Asia/Jakarta';
        $localDate = $playDate->setTimezone($timezone)->startOfDay();

        $operatingHour = OperatingHour::query()
            ->withoutGlobalScope(VenueScope::class)
            ->where('venue_id', $venue->id)
            ->where('weekday', $localDate->dayOfWeek)
            ->first();

        if ($operatingHour === null || $operatingHour->is_closed) {
            return [];
        }

        $opensMin = $operatingHour->opens_min;
        $closesMin = $operatingHour->closes_min;

        if ($closesMin - $opensMin < 60) {
            return [];
        }

        $windowStartUtc = $localDate->addMinutes($opensMin)->utc();
        $windowEndUtc = $localDate->addMinutes($closesMin)->utc();

        $lockedBookings = Booking::query()
            ->withoutGlobalScopes()
            ->where('court_id', $court->id)
            ->whereIn('status', Booking::LOCKED_STATUSES)
            ->where('starts_at', '<', $windowEndUtc)
            ->where('ends_at', '>', $windowStartUtc)
            ->get(['id', 'starts_at', 'ends_at']);

        $scheduleBlocks = ScheduleBlock::query()
            ->withoutGlobalScopes()
            ->where('court_id', $court->id)
            ->where('starts_at', '<', $windowEndUtc)
            ->where('ends_at', '>', $windowStartUtc)
            ->get(['id', 'starts_at', 'ends_at']);

        $dayType = SlotPricing::dayTypeForWeekday($localDate->dayOfWeek);

        /** @var Collection<int, PriceRule> $priceRules */
        $priceRules = PriceRule::query()
            ->where('court_id', $court->id)
            ->where('day_type', $dayType)
            ->orderBy('start_min')
            ->get();

        $nowUtc = CarbonImmutable::now('UTC');
        $slots = [];

        for ($startMin = $opensMin; $startMin + 60 <= $closesMin; $startMin += 60) {
            $endMin = $startMin + 60;
            $slotStartUtc = $localDate->addMinutes($startMin)->utc();
            $slotEndUtc = $localDate->addMinutes($endMin)->utc();

            $rule = SlotPricing::findRuleForHour($priceRules, $startMin, $endMin);
            $price = $rule?->price_per_hour;

            $isBooked = $lockedBookings->contains(
                fn (Booking $booking): bool => $booking->starts_at->lt($slotEndUtc) && $booking->ends_at->gt($slotStartUtc)
            );

            $isBlocked = $scheduleBlocks->contains(
                fn (ScheduleBlock $block): bool => $block->starts_at->lt($slotEndUtc) && $block->ends_at->gt($slotStartUtc)
            );

            $isPast = $slotStartUtc->lessThanOrEqualTo($nowUtc);

            $unavailableReason = null;
            if ($isPast) {
                $unavailableReason = 'past';
            } elseif ($isBlocked) {
                $unavailableReason = 'blocked';
            } elseif ($isBooked) {
                $unavailableReason = 'booked';
            } elseif ($price === null) {
                $unavailableReason = 'no_price';
            }

            $slots[] = [
                'start_min' => $startMin,
                'end_min' => $endMin,
                'label' => SlotPricing::formatMinuteLabel($startMin).'–'.SlotPricing::formatMinuteLabel($endMin),
                'starts_at' => $slotStartUtc->toIso8601String(),
                'ends_at' => $slotEndUtc->toIso8601String(),
                'price' => $price,
                'is_available' => $unavailableReason === null,
                'unavailable_reason' => $unavailableReason,
            ];
        }

        return $slots;
    }
}
