<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryDaySummaryData;
use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\Enums\TimeEntryDailyStatus;
use App\Models\User;

/**
 * GET stats/overview and stats/pulse (spec 0122, D-11, AC-017/AC-018), both
 * built on the same `TimeEntryDaySetBuilder` day set as the list. The two
 * formulas read directly off D-11's own wording:
 *
 *   - overview: `tracked_minutes` sums totals over the DAY-FILTERED set
 *     ("giorni filtrati", is_active/daily_statuses applied) INCLUDING
 *     non-working days; `tracked_days` counts only the filtered days that
 *     actually HAVE a segnatempo (`is_active`, mirrors qnet's own
 *     `$trackedDays = count(filtered where hasActivity)` — a day with zero
 *     minutes still contributes 0 to the sum but does not count as a
 *     "tracked" day, or `average_daily_focus_minutes` would be diluted by
 *     empty days); `period_target_minutes`/`working_days` run on the FULL
 *     period's working days instead (D-11 names them "del periodo", never
 *     "filtrati" — the day filter never removes a working day from this
 *     denominator); `anomalies` intersects both (filtered AND working);
 *     `daily_target_minutes` is the LAST working day's target in the full
 *     period, or the same "target di oggi" figure `meta.
 *     daily_target_minutes` uses when the period has none.
 *   - pulse: skips the day filter entirely (D-11: "IGNORA daily_statuses e
 *     is_active"), restricts to the period's working days, and clusters
 *     ENTRY minutes by task type across them.
 */
final class TimeEntryStatsService
{
    public function __construct(
        private readonly TimeEntryDaySetBuilder $daySetBuilder,
        private readonly TimeEntryDayBuilder $dayBuilder,
        private readonly TimeEntryClusterCalculator $clusterCalculator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(TimeEntryFilterData $filter, User $actor): array
    {
        $set = $this->daySetBuilder->build($filter, $actor);

        $filteredDays = $this->dayBuilder->filterByActivity($set->days, $filter);
        $trackedMinutes = $this->sumMinutes($filteredDays);
        $trackedDays = count(array_filter($filteredDays, static fn (TimeEntryDaySummaryData $day): bool => $day->isActive));

        $workingDays = $this->onlyWorkingDays($set->days);
        $periodTargetMinutes = $this->sumTargets($workingDays);

        $filteredWorkingDays = $this->onlyWorkingDays($filteredDays);

        return [
            'period' => ['date_from' => $set->period->dateFrom, 'date_to' => $set->period->dateTo],
            'daily_target_minutes' => $this->lastWorkingDayTarget($workingDays) ?? $set->baseTargetMinutes,
            'period_target_minutes' => $periodTargetMinutes,
            'working_days' => count($workingDays),
            'tracked_minutes' => $trackedMinutes,
            'tracked_days' => $trackedDays,
            'average_daily_focus_minutes' => $trackedDays > 0 ? (int) round($trackedMinutes / $trackedDays) : 0,
            'anomalies' => [
                'over_target_days' => $this->countByStatus($filteredWorkingDays, TimeEntryDailyStatus::OverTarget),
                'under_target_days' => $this->countByStatus($filteredWorkingDays, TimeEntryDailyStatus::UnderTarget),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function pulse(TimeEntryFilterData $filter, User $actor): array
    {
        $set = $this->daySetBuilder->build($filter, $actor);
        $workingDays = $this->onlyWorkingDays($set->days);

        $trackedMinutes = $this->sumMinutes($workingDays);
        $targetMinutes = $this->sumTargets($workingDays);
        $clusters = $this->buildClusters($workingDays, $trackedMinutes);

        return [
            'coverage' => [
                'percentage' => $targetMinutes > 0 ? (int) round($trackedMinutes / $targetMinutes * 100) : 0,
                'working_days' => count($workingDays),
                'tracked_days' => count(array_filter($workingDays, static fn (TimeEntryDaySummaryData $day): bool => $day->isActive)),
            ],
            'primary_cluster' => $clusters[0] ?? null,
            'other_clusters' => array_slice($clusters, 1),
        ];
    }

    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     * @return list<TimeEntryDaySummaryData>
     */
    private function onlyWorkingDays(array $days): array
    {
        return array_values(array_filter($days, static fn (TimeEntryDaySummaryData $day): bool => ! $day->isNonWorkingDay));
    }

    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     */
    private function sumMinutes(array $days): int
    {
        return array_sum(array_map(static fn (TimeEntryDaySummaryData $day): int => $day->totalMinutes, $days));
    }

    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     */
    private function sumTargets(array $days): int
    {
        return array_sum(array_map(static fn (TimeEntryDaySummaryData $day): int => $day->targetMinutes, $days));
    }

    /**
     * @param  list<TimeEntryDaySummaryData>  $days
     */
    private function countByStatus(array $days, TimeEntryDailyStatus $status): int
    {
        return count(array_filter($days, static fn (TimeEntryDaySummaryData $day): bool => $day->status === $status));
    }

    /**
     * $workingDays is chronological ascending (built off TimeEntryPeriod::
     * dates()), so the last element IS the period's last working day.
     *
     * @param  list<TimeEntryDaySummaryData>  $workingDays
     */
    private function lastWorkingDayTarget(array $workingDays): ?int
    {
        if ($workingDays === []) {
            return null;
        }

        return end($workingDays)->targetMinutes;
    }

    /**
     * Flattens every entry of $workingDays into the single formula
     * `TimeEntryClusterCalculator` shares with `TimeEntryTeamPulseService`
     * (D-11) — this method's own job is ONLY the day -> entries flattening,
     * never the cluster math itself.
     *
     * @param  list<TimeEntryDaySummaryData>  $workingDays
     * @return list<array{task_type: array{id: int, name: string, color: ?string, icon: ?string}, minutes: int, percentage: int}>
     */
    private function buildClusters(array $workingDays, int $totalTrackedMinutes): array
    {
        $entries = [];

        foreach ($workingDays as $day) {
            foreach ($day->entries as $entry) {
                $entries[] = $entry;
            }
        }

        return $this->clusterCalculator->calculate($entries, $totalTrackedMinutes);
    }
}
