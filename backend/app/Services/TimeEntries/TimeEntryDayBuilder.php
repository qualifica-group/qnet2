<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\DayAbsenceData;
use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Enums\TimeEntryDailyStatus;
use App\Models\TimeEntry;
use App\Services\Absences\Calendar\PersonNonWorkingDays;
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
     * @param  array<string, list<DayAbsenceData>>  $absencesByDate  approved absences of the person, by date
     * @param  PersonNonWorkingDays  $nonWorking  weekend, holidays and the closures of the person (spec 0304 D-14)
     * @return list<TimeEntryDaySummaryData>
     */
    public function build(array $dates, Collection $entries, array $notesByDate, int $baseTargetMinutes, PersonNonWorkingDays $nonWorking, int $userId, array $absencesByDate = []): array
    {
        $entriesByDate = $entries->groupBy(static fn (TimeEntry $entry): string => $entry->date->format('Y-m-d'));

        return array_map(
            fn (string $date): TimeEntryDaySummaryData => $this->buildDay(
                $date,
                $entriesByDate->get($date, collect()),
                $notesByDate[$date] ?? null,
                $baseTargetMinutes,
                $nonWorking,
                $userId,
                $absencesByDate[$date] ?? [],
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
     * @param  list<DayAbsenceData>  $absences
     */
    private function buildDay(string $date, Collection $dayEntries, ?string $note, int $baseTargetMinutes, PersonNonWorkingDays $nonWorking, int $userId, array $absences): TimeEntryDaySummaryData
    {
        $sortedEntries = $dayEntries->sort(function (TimeEntry $a, TimeEntry $b): int {
            $byStartTime = $this->startTimeSortKey($a) <=> $this->startTimeSortKey($b);

            return $byStartTime !== 0 ? $byStartTime : $a->id <=> $b->id;
        })->values();

        $isNonWorkingDay = $nonWorking->isNonWorkingDay($userId, $date);
        $targetMinutes = $isNonWorkingDay ? 0 : DayAbsenceIndex::reduceTarget($baseTargetMinutes, $absences);
        $reason = $nonWorking->reason($userId, $date);
        $closureName = $reason !== null && $reason['reason'] === PersonNonWorkingDays::REASON_CLOSURE ? $reason['name'] : null;
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
            absences: $absences,
            entries: $sortedEntries,
            closure: $closureName === null ? null : ['name' => $closureName],
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
