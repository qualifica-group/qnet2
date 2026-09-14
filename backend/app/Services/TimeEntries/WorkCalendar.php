<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use Carbon\CarbonImmutable;

/**
 * Italian working-day calendar (spec 0122, D-7): weekends plus the ten fixed
 * civil/religious holidays plus Easter Sunday/Monday. Pure and DB-free — a
 * single testable unit, reused by the day builder and (later) exports.
 *
 * Dates are plain `Y-m-d` strings throughout, deliberately never touching a
 * timezone-aware `now()`: a calendar day is a calendar concept, not an
 * instant, so there is nothing here for a UTC/Europe-Rome mismatch to
 * corrupt — this is what fixes the qnet bug D-7 calls out (qnet computed
 * Easter off a UTC Unix timestamp, off by a day close to midnight CET).
 * `easter_days()` (ext-calendar, confirmed available) returns the number of
 * days after March 21 Easter Sunday falls on, computed from the year alone —
 * no timestamp involved either.
 */
final class WorkCalendar
{
    /**
     * @var list<string> "m-d" fixed holidays (D-7).
     */
    private const array FIXED_HOLIDAYS = [
        '01-01', '01-06', '04-25', '05-01', '06-02', '08-15', '11-01', '12-08', '12-25', '12-26',
    ];

    public function isNonWorkingDay(string $date): bool
    {
        return $this->isWeekend($date) || $this->isHoliday($date);
    }

    public function isWeekend(string $date): bool
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $date)->dayOfWeekIso >= 6;
    }

    public function isHoliday(string $date): bool
    {
        if (in_array(substr($date, 5, 5), self::FIXED_HOLIDAYS, true)) {
            return true;
        }

        $year = (int) substr($date, 0, 4);

        return $date === $this->easterSunday($year) || $date === $this->easterMonday($year);
    }

    private function easterSunday(int $year): string
    {
        return CarbonImmutable::createFromDate($year, 3, 21)->addDays(easter_days($year))->format('Y-m-d');
    }

    private function easterMonday(int $year): string
    {
        return CarbonImmutable::createFromDate($year, 3, 21)->addDays(easter_days($year) + 1)->format('Y-m-d');
    }
}
