<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;

/**
 * The two small pivot-delta reads App\Services\TaskService::update() needs
 * for TaskWatcherOverlapGuard's RESULTING-value fallback (spec 0118 D-9,
 * AC-033) and for the "only the newcomers hear about it" notification rule
 * (spec 0119 D-9) — split out purely for TaskService's own file size
 * (engineering.md §6), not because either read has logic of its own worth
 * hiding: both are one-line queries over a Task's `assignees`/`watchers`
 * pivot.
 */
final class TaskPivotDelta
{
    /**
     * The ids currently on one of the Task's two user pivots, used as a
     * RESULTING-value fallback when the pivot's own key was not part of a
     * PATCH — the guard is then judged on what the Task will actually hold
     * once saved, rather than on the submitted keys alone.
     *
     * @param  'assignees'|'watchers'  $relation
     * @return array<int, int>
     */
    public function persistedIds(Task $task, string $relation): array
    {
        $query = match ($relation) {
            'assignees' => $task->assignees(),
            'watchers' => $task->watchers(),
        };

        return $query->pluck('users.id')->all();
    }

    /**
     * The ids a PATCH ADDS to one of the two user pivots: on an update only
     * the newcomers are notified, never those already on the pivot and
     * never those being removed. MUST be called BEFORE the sync — a key that
     * was not submitted leaves the pivot untouched and therefore adds
     * nobody.
     *
     * @param  'assignees'|'watchers'  $relation
     * @param  array<int, int>|null  $submittedIds  null = key not submitted
     * @return array<int, int>
     */
    public function addedIds(Task $task, string $relation, ?array $submittedIds): array
    {
        if ($submittedIds === null) {
            return [];
        }

        return array_values(array_diff($submittedIds, $this->persistedIds($task, $relation)));
    }
}
