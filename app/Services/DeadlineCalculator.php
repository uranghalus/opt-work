<?php

namespace App\Services;

use App\WorkOrderCategory;
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
    public function calculate(WorkOrderCategory $category, Carbon $assignedAt, ?array $holidays = null): Carbon
    {
        $remaining = $category->isUrgent()
            ? self::URGENT_BUSINESS_DAYS
            : self::NORMAL_BUSINESS_DAYS;

        $holidayLookup = array_flip($holidays ?? config('holidays', []));

        $cursor = $assignedAt->copy()->startOfDay();

        while ($remaining > 0) {
            $cursor->addDay();

            if ($this->isBusinessDay($cursor, $holidayLookup)) {
                $remaining--;
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
