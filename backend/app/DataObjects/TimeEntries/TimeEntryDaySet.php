<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

use App\Models\User;

/**
 * The result of `TimeEntryDaySetBuilder::build()`: the authorized selected
 * user (rule R), the resolved period, that user's base (calendar-agnostic)
 * daily target and the full day list for the period — BEFORE the day-level
 * `is_active`/`daily_statuses` filter, which the list/overview endpoints
 * apply themselves and pulse deliberately skips (D-11).
 *
 * Shared foundation for GET /api/time-entries, stats/overview and
 * stats/pulse (spec 0122, MT-B3), so the three never resolve rule R, the
 * period or the day-building differently.
 */
final readonly class TimeEntryDaySet
{
    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     */
    public function __construct(
        public User $selectedUser,
        public TimeEntryPeriod $period,
        public int $baseTargetMinutes,
        public array $days,
    ) {}
}
