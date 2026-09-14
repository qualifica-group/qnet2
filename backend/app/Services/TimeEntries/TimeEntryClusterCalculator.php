<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\TaskType;
use App\Models\TimeEntry;

/**
 * D-11's cluster formula: minutes per `task_type` across a set of entries,
 * DESC by minutes, `percentage` relative to $totalMinutes. THE single
 * implementation, shared by `TimeEntryStatsService::pulse()` (one user, entries
 * drawn from a day set) and `TimeEntryTeamPulseService` (one call per member,
 * entries drawn from a batch query) — a flat `iterable<TimeEntry>` is the only
 * shape both callers agree on, so the formula itself never sees a "day" or a
 * "user".
 */
final class TimeEntryClusterCalculator
{
    /**
     * @param  iterable<TimeEntry>  $entries
     * @return list<array{task_type: array{id: int, name: string, color: ?string, icon: ?string}, minutes: int, percentage: int}>
     */
    public function calculate(iterable $entries, int $totalMinutes): array
    {
        /** @var array<int, array{type: TaskType, minutes: int}> $byTaskTypeId */
        $byTaskTypeId = [];

        foreach ($entries as $entry) {
            $taskType = $entry->taskType;
            $byTaskTypeId[$taskType->id] ??= ['type' => $taskType, 'minutes' => 0];
            $byTaskTypeId[$taskType->id]['minutes'] += $entry->minutes;
        }

        $clusters = array_map(
            static fn (array $row): array => [
                'task_type' => [
                    'id' => $row['type']->id,
                    'name' => $row['type']->name,
                    'color' => $row['type']->color,
                    'icon' => $row['type']->icon,
                ],
                'minutes' => $row['minutes'],
                'percentage' => $totalMinutes > 0 ? (int) round($row['minutes'] / $totalMinutes * 100) : 0,
            ],
            array_values($byTaskTypeId),
        );

        usort($clusters, static fn (array $a, array $b): int => $b['minutes'] <=> $a['minutes']);

        return $clusters;
    }
}
