<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Enums\TimeEntryDailyStatus;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds one `TimeEntryDaySummaryData` per date of the period (spec 0122,
 * data_contract GET /api/time-entries — "un DaySummary per OGNI data del
 * periodo, anche senza segnatempo") and, separately, applies the day-level
 * `is_active`/`daily_statuses` filter. Pure: $entries/$notesByDate are
 * assumed already scoped/eager-loaded by the caller (`TimeEntryDaySetBuilder`)
 * — this class runs zero queries.
 */
final class TimeEntryDayBuilder
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @param  list<string>  $dates
     * @param  Collection<int, TimeEntry>  $entries  entry-level (F) filtered, DETAIL_RELATIONS eager-loaded
     * @param  array<string, string>  $notesByDate
     * @return list<TimeEntryDaySummaryData>
     */
    public function build(array $dates, Collection $entries, array $notesByDate, int $baseTargetMinutes): array
    {
        $entriesByDate = $entries->groupBy(static fn (TimeEntry $entry): string => $entry->date->format('Y-m-d'));

        return array_map(
            fn (string $date): TimeEntryDaySummaryData => $this->buildDay(
                $date,
                $entriesByDate->get($date, collect()),
                $notesByDate[$date] ?? null,
                $baseTargetMinutes,
            ),
            $dates,
        );
    }

    /**
     * The day-level filter (data_contract: "poi filtri is_active e
     * daily_statuses"), applied to an already-built day list. Kept here
     * (not in the list Service) so overview can reuse it verbatim — pulse
     * deliberately skips this call entirely (D-11: "IGNORA daily_statuses e
     * is_active").
     *
     * @param  list<TimeEntryDaySummaryData>  $days
     * @return list<TimeEntryDaySummaryData>
     */
    public function filterByActivity(array $days, TimeEntryFilterData $filter): array
    {
        return array_values(array_filter(
            $days,
            static function (TimeEntryDaySummaryData $day) use ($filter): bool {
                if ($filter->isActive !== null && $day->isActive !== $filter->isActive) {
                    return false;
                }

                return $filter->dailyStatuses === [] || in_array($day->status->value, $filter->dailyStatuses, true);
            },
        ));
    }

    /**
     * @param  Collection<int, TimeEntry>  $dayEntries
     */
    private function buildDay(string $date, Collection $dayEntries, ?string $note, int $baseTargetMinutes): TimeEntryDaySummaryData
    {
        $sortedEntries = $dayEntries->sort(function (TimeEntry $a, TimeEntry $b): int {
            $byStartTime = $this->startTimeSortKey($a) <=> $this->startTimeSortKey($b);

            return $byStartTime !== 0 ? $byStartTime : $a->id <=> $b->id;
        })->values();

        $isNonWorkingDay = $this->calendar->isNonWorkingDay($date);
        $targetMinutes = $isNonWorkingDay ? 0 : $baseTargetMinutes;
        $totalMinutes = (int) $sortedEntries->sum('minutes');

        return new TimeEntryDaySummaryData(
            date: $date,
            weekday: CarbonImmutable::createFromFormat('Y-m-d', $date)->dayOfWeekIso,
            isHoliday: $this->calendar->isHoliday($date),
            isNonWorkingDay: $isNonWorkingDay,
            isActive: $sortedEntries->isNotEmpty(),
            targetMinutes: $targetMinutes,
            totalMinutes: $totalMinutes,
            utilizationPercentage: $targetMinutes > 0 ? round($totalMinutes / $targetMinutes * 100, 2) : 0.0,
            status: TimeEntryDailyStatus::forTotals($targetMinutes, $totalMinutes, $isNonWorkingDay),
            dayNote: $note,
            entries: $sortedEntries,
        );
    }

    /**
     * `null` (no `start_time`) sorts LAST (data_contract: "start_time asc,
     * null in coda").
     */
    private function startTimeSortKey(TimeEntry $entry): string
    {
        return $entry->start_time ?? '24:00:00';
    }
}
