<?php

namespace App\Services;

use App\WorkOrderCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Business-day deadline calculator (FR-2.1): urgent work orders are due in
 * 3 business days, normal ones in 6 — counted from the assign date, skipping
 * weekends and the holidays configured in config/holidays.php (fixed
 * 'Y-m-d' dates or recurring 'MM-DD' entries).
 */
class DeadlineCalculator
{
    private const URGENT_BUSINESS_DAYS = 3;

    private const NORMAL_BUSINESS_DAYS = 6;

    /**
     * @param  list<string>|null  $holidays  overrides config('holidays') —
     *                                       lets tests pin the calendar
     */
    public function calculate(WorkOrderCategory $category, CarbonInterface $assignedAt, ?array $holidays = null): Carbon
    {
        $remaining = $category->isUrgent()
            ? self::URGENT_BUSINESS_DAYS
            : self::NORMAL_BUSINESS_DAYS;

        $holidayLookup = array_flip($holidays ?? config('holidays', []));

        // The app runs CarbonImmutable dates; normalize to a mutable cursor.
        $cursor = Carbon::instance($assignedAt)->startOfDay();

        while ($remaining > 0) {
            $cursor->addDay();

            if ($this->isBusinessDay($cursor, $holidayLookup)) {
                $remaining--;
            }
        }

        return $cursor;
    }

    /**
     * Adds N business days to a date, skipping weekends and configured
     * holidays — the extend flow (FR-2.3) moves the deadline the same way
     * the SLA clock counts it.
     *
     * @param  list<string>|null  $holidays  overrides config('holidays') —
     *                                       lets tests pin the calendar
     */
    public function addBusinessDays(CarbonInterface $from, int $days, ?array $holidays = null): Carbon
    {
        $holidayLookup = array_flip($holidays ?? config('holidays', []));

        // The app runs CarbonImmutable dates; normalize to a mutable cursor.
        $cursor = Carbon::instance($from)->startOfDay();

        while ($days > 0) {
            $cursor->addDay();

            if ($this->isBusinessDay($cursor, $holidayLookup)) {
                $days--;
            }
        }

        return $cursor;
    }

    /**
     * @param  array<int, int>  $holidayLookup
     */
    private function isBusinessDay(Carbon $date, array $holidayLookup): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! isset($holidayLookup[$date->toDateString()])
            && ! isset($holidayLookup[$date->format('m-d')]);
    }
}
