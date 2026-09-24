<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * WHEN a domain action makes sense, driven by the PHASE of the Task's
 * CURRENT status (spec 0116, D-1) — never a label. `is_blocked` is read
 * only by the two methods that name it directly: the veto it casts over
 * every OTHER action is a D-8 business rule, not availability, and lives in
 * App\Services\Tasks\TaskWriteLock::assertNotBlocked(), which answers 409 for it.
 *
 * Modelled on App\Services\Contracts\ContractActionAvailability: an
 * availability rule, NOT an authorization one. Each caller
 * (App\Authorization\TasksAuthorization, App\Services\Tasks\TaskCompletionService, App\Services\Tasks\TaskActionService)
 * still ANDs it with the actor's ability (TaskAbilityResolver), and
 * TaskCompletionService/TaskActionService re-assert the same rules server-side (422).
 *
 * Injectable, unlike TaskRecordRoles/TaskAbilityResolver: only
 * TasksAuthorization, TaskCompletionService and TaskActionService consume it, never the Policy.
 */
class TaskActionAvailability
{
    /** Attribute withOpenSubtasksCount() preloads. */
    public const string OPEN_SUBTASKS_COUNT = 'open_subtasks_count';

    /** Not already in a closing phase and not already awaiting validation. */
    public function isCompletable(Task $task): bool
    {
        return ! $this->isClosedOrInValidation($task);
    }

    /** Already sitting in a closing phase or awaiting validation. */
    public function isUncompletable(Task $task): bool
    {
        return $this->isClosedOrInValidation($task);
    }

    public function isValidatable(Task $task): bool
    {
        return $this->group($task) === TaskStatusGroup::InValidation;
    }

    /**
     * D-7 (spec 0153, REQUIREMENT CHANGED): not already blocked AND the Task
     * is open (not completed, not awaiting validation) — `isCompletable()`
     * answers the exact same phase window, reused rather than re-derived.
     */
    public function isBlockable(Task $task): bool
    {
        return $task->is_blocked === false && $this->isCompletable($task);
    }

    public function isUnblockable(Task $task): bool
    {
        return $task->is_blocked === true;
    }

    /**
     * D-6 (spec 0123): whether $task has at least one DIRECT sub-task
     * sitting outside a closing phase (`in_validation` counts as open).
     * Backs the 422 on `/complete`/`/approve` and the AND on
     * `permissions.actions.complete`/`complete_to_validation`/`approve`.
     */
    public function hasOpenSubtasks(Task $task): bool
    {
        return $this->openSubtasksCount($task) > 0;
    }

    /**
     * The count `TaskResource` exposes as `data.open_subtasks_count`
     * (data_contract). ONE query against `task_statuses.group`, never the
     * loaded `subtasks` relation: counting must ignore
     * `TaskVisibilityScope` (AC-021), the same rule
     * `App\Services\TaskService::delete()` already applies to child
     * counting, so a plain `whereHas` against the unscoped relation is what
     * both call sites need.
     */
    public function openSubtasksCount(Task $task): int
    {
        // Preloaded for a whole collection by withOpenSubtasksCount(): one
        // query for every sub-task row instead of one per row.
        if (array_key_exists(self::OPEN_SUBTASKS_COUNT, $task->getAttributes())) {
            return (int) $task->getAttribute(self::OPEN_SUBTASKS_COUNT);
        }

        return $task->subtasks()
            ->whereHas('taskStatus', fn ($query) => $query->whereNotIn('group', [
                TaskStatusGroup::ClosedPositive->value,
                TaskStatusGroup::ClosedNegative->value,
            ]))
            ->count();
    }

    /**
     * Preloads openSubtasksCount() for every Task the query returns, with the
     * same unscoped predicate.
     *
     * @template TModel of Task
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function withOpenSubtasksCount(Builder $query): Builder
    {
        return $query->withCount(['subtasks as '.self::OPEN_SUBTASKS_COUNT => fn (Builder $subtasks) => $subtasks->whereHas(
            'taskStatus',
            fn (Builder $status) => $status->whereNotIn('group', [
                TaskStatusGroup::ClosedPositive->value,
                TaskStatusGroup::ClosedNegative->value,
            ]),
        )]);
    }

    private function isClosedOrInValidation(Task $task): bool
    {
        return in_array($this->group($task), [
            TaskStatusGroup::InValidation,
            TaskStatusGroup::ClosedPositive,
            TaskStatusGroup::ClosedNegative,
        ], true);
    }

    private function group(Task $task): ?TaskStatusGroup
    {
        $task->loadMissing('taskStatus');

        return $task->taskStatus?->group;
    }
}
