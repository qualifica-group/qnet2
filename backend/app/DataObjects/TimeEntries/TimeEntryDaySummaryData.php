<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

use App\Enums\TimeEntryDailyStatus;
use App\Models\TimeEntry;
use Illuminate\Support\Collection;

/**
 * One day of the dashboard (spec 0122, data_contract `DaySummary`), built by
 * `TimeEntryDayBuilder`. `entries` already carries only the rows that pass
 * the entry-level filters F (AC-014) and is sorted `start_time` asc/null
 * last/`id` asc — the shape `TimeEntryDaySummaryResource` reads verbatim.
 */
final readonly class TimeEntryDaySummaryData
{
    /**
     * @param  Collection<int, TimeEntry>  $entries
     * @param  list<DayAbsenceData>  $absences  approved absences covering the day (spec 0278)
     * @param  array{name: string}|null  $closure  the holiday closure that makes the day non-working for the person (spec 0304)
     */
    public function __construct(
        public string $date,
        public int $weekday,
        public bool $isHoliday,
        public bool $isNonWorkingDay,
        public bool $isActive,
        public int $targetMinutes,
        public int $totalMinutes,
        public float $utilizationPercentage,
        public TimeEntryDailyStatus $status,
        public ?string $dayNote,
        public Collection $entries,
        public array $absences = [],
        public ?array $closure = null,
    ) {}
}
