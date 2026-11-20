<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Http\Requests\TimeEntries\BulkTimeEntrySharesRules;

/**
 * Turns the TOTAL time entry of a bulk complete (spec 0338) into one raw
 * time entry per task, ready for TaskBulkActionExecutor::complete(): minutes
 * become the task's share and, when the total has a start time, each task
 * gets the next consecutive slot in the order of the shares.
 */
final class TaskBulkTimeEntrySplitter
{
    /**
     * @param  array<string, mixed>  $timeEntry
     * @param  array<int, array{task_id: int, minutes: int}>  $shares
     * @return array<int, array<string, mixed>> keyed by task id
     */
    public function split(array $timeEntry, array $shares): array
    {
        $cursor = isset($timeEntry['start_time']) ? BulkTimeEntrySharesRules::toMinutes((string) $timeEntry['start_time']) : null;
        $perTask = [];

        foreach ($shares as $share) {
            $entry = $timeEntry;
            $entry['minutes'] = $share['minutes'];
            $entry['start_time'] = $cursor === null ? null : $this->format($cursor);
            $entry['end_time'] = $cursor === null ? null : $this->format($cursor + $share['minutes']);

            if ($cursor !== null) {
                $cursor += $share['minutes'];
            }

            $perTask[$share['task_id']] = $entry;
        }

        return $perTask;
    }

    /**
     * The even split the bulk dialog proposes by default (frontend
     * `splitMinutesEvenly`): the remainder goes one minute at a time to the
     * first tasks, so the shares always add up to the total. A total smaller
     * than the task count yields 0-minute shares, rejected by the share rules.
     *
     * @param  array<int, int>  $taskIds
     * @return array<int, array{task_id: int, minutes: int}>
     */
    public function evenShares(int $total, array $taskIds): array
    {
        $count = count($taskIds);

        if ($count === 0) {
            return [];
        }

        $base = intdiv($total, $count);
        $remainder = $total % $count;

        return array_map(
            fn (int $taskId, int $index): array => ['task_id' => $taskId, 'minutes' => $base + ($index < $remainder ? 1 : 0)],
            array_values($taskIds),
            array_keys(array_values($taskIds)),
        );
    }

    /** The request already keeps the last slot within 23:59 (BulkTimeEntrySharesRules). */
    private function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
