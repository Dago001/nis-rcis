<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Working days (Monday to Friday, excluding the public holidays listed in
 * config nis.public_holidays) for service-level targets.
 */
class WorkingDays
{
    /** Whole working days elapsed from $from to $to (the start day is not counted). */
    public static function between(DateTimeInterface $from, ?DateTimeInterface $to = null): int
    {
        $day = CarbonImmutable::instance($from)->startOfDay();
        $end = CarbonImmutable::instance($to ?? now())->startOfDay();
        $count = 0;
        while ($day < $end) {
            $day = $day->addDay();
            if (self::isWorkingDay($day)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Anything submitted before the returned moment has waited more than
     * $days working days by $now (the day between() counts from is excluded).
     */
    public static function cutoff(int $days, ?DateTimeInterface $now = null): CarbonImmutable
    {
        $day = CarbonImmutable::instance($now ?? now())->startOfDay();
        $seen = 0;
        while (true) {
            if (self::isWorkingDay($day) && ++$seen === $days + 1) {
                return $day;
            }
            $day = $day->subDay();
        }
    }

    public static function isWorkingDay(CarbonImmutable $day): bool
    {
        return ! $day->isWeekend() && ! in_array($day->toDateString(), config('nis.public_holidays', []), true);
    }
}
