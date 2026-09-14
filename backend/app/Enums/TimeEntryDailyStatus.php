<?php

namespace App\Enums;

/**
 * A day's target-vs-tracked outcome (spec 0122, D-7/data_contract
 * `DailyStatus`). `forTotals()` is THE single implementation of D-7's rule
 * — shared by the day list, overview's anomaly count and the `daily_statuses`
 * filter, so the four thresholds never drift between them.
 */
enum TimeEntryDailyStatus: string
{
    case NoTarget = 'no_target';
    case UnderTarget = 'under_target';
    case OnTarget = 'on_target';
    case OverTarget = 'over_target';

    /**
     * D-7: a non-working day or a zero target is always `no_target`
     * (regardless of any minutes logged on it); otherwise an exact-to-the-
     * minute comparison, no tolerance.
     */
    public static function forTotals(int $targetMinutes, int $totalMinutes, bool $isNonWorkingDay): self
    {
        if ($isNonWorkingDay || $targetMinutes === 0) {
            return self::NoTarget;
        }

        return match (true) {
            $totalMinutes < $targetMinutes => self::UnderTarget,
            $totalMinutes > $targetMinutes => self::OverTarget,
            default => self::OnTarget,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
