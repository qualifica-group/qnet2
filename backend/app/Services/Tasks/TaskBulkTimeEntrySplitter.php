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

    /** The request already keeps the last slot within 23:59 (BulkTimeEntrySharesRules). */
    private function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
