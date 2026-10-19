<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Monthly accrual of an absence allowance (spec 0278 D-10), pure: the
 * reference date is always a parameter, never read from the clock.
 *
 * Month M of year Y accrues 1/12 of the allowance when it has already started
 * relative to the reference date (past years: every month; future years:
 * none) AND the person is employed at least MIN_DAYS_IN_FORCE days in it.
 * Without `hired_at` the person counts as employed all along; an open
 * `terminated_at` end means no end.
 */
final class AbsenceAccrualCalculator
{
    public const int MIN_DAYS_IN_FORCE = 15;

    private const int MONTHS_IN_YEAR = 12;

    public function accruedMinutes(int $annualMinutes, int $months): int
    {
        return (int) round($annualMinutes * $months / self::MONTHS_IN_YEAR);
    }

    public function accruedMonths(int $year, ?CarbonInterface $hiredAt, ?CarbonInterface $terminatedAt, CarbonInterface $today): int
    {
        $months = 0;

        for ($month = 1; $month <= $this->lastStartedMonth($year, $today); $month++) {
            if ($this->daysInForce($year, $month, $hiredAt, $terminatedAt) >= self::MIN_DAYS_IN_FORCE) {
                $months++;
            }
        }

        return $months;
    }

    private function lastStartedMonth(int $year, CarbonInterface $today): int
    {
        return match (true) {
            $year < $today->year => self::MONTHS_IN_YEAR,
            $year === $today->year => $today->month,
            default => 0,
        };
    }

    private function daysInForce(int $year, int $month, ?CarbonInterface $hiredAt, ?CarbonInterface $terminatedAt): int
    {
        $first = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $last = $first->endOfMonth()->startOfDay();

        $from = $hiredAt !== null && $hiredAt->copy()->startOfDay()->greaterThan($first) ? $hiredAt->copy()->startOfDay() : $first;
        $to = $terminatedAt !== null && $terminatedAt->copy()->startOfDay()->lessThan($last) ? $terminatedAt->copy()->startOfDay() : $last;

        return $to->lessThan($from) ? 0 : (int) $from->diffInDays($to) + 1;
    }
}
