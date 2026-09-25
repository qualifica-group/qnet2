<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CompleteTaskData;
use App\DataObjects\Tasks\UpdateTaskData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Support\Facades\Gate;

/**
 * The per-task write behind every bulk Task action: App\Services\WorkOrders\
 * TaskBulkActionService (spec 0146 D-7's task-board bulk, best-effort) and
 * App\Services\Tasks\TaskBulkService (spec 0156 D-6's generic
 * `POST /api/tasks/bulk`, all-or-nothing) both call these SAME methods, one
 * task at a time, so the two endpoints can never diverge on what "assign"/
 * "complete"/... actually does. Each method re-asserts its own Gate ability
 * (D-9) and delegates to the SAME domain Service a single-task endpoint uses
 * — never a shortcut around it.
 *
 * Neither commits nor rolls back here: the caller's own transaction shape
 * (per-task best-effort for the board, a savepoint-per-task inside one outer
 * transaction for the generic endpoint) decides that.
 */
final class TaskBulkActionExecutor
{
    public function __construct(
        private readonly TaskService $taskService,
        private readonly TaskCompletionService $completionService,
        private readonly TaskActionService $actionService,
    ) {}

    /**
     * @param  array<int, int>  $assigneeIds
     */
    public function assign(Task $task, array $assigneeIds, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $this->taskService->update($task, UpdateTaskData::fromValidated([
            'assignee_ids' => $assigneeIds,
        ]), $actor);
    }

    /**
     * `$timeEntry` is null when the caller submitted none (spec 0162, D-2/D-4:
     * `POST /api/tasks/bulk`'s `time_entry` is optional) — the payload then
     * carries no `time_entry` key at all, so `CompleteTaskData::fromValidated()`
     * reads it the SAME way a single-task request omitting the field would,
     * and `TaskCompletionService::complete()` re-asserts whether that is
     * actually allowed for $task.
     *
     * @param  array<string, mixed>|null  $timeEntry
     */
    public function complete(
        Task $task,
        ?array $timeEntry,
        ?string $closureFeedback,
        bool $closureFeedbackSubmitted,
        ?int $validationStatusId,
        bool $forAllAssignees,
        User $actor,
    ): void {
        Gate::forUser($actor)->authorize('complete', $task);

        $payload = ['for_all_assignees' => $forAllAssignees];

        if ($timeEntry !== null) {
            $payload['time_entry'] = $timeEntry;
        }

        if ($closureFeedbackSubmitted) {
            $payload['closure_feedback'] = $closureFeedback;
        }

        if ($validationStatusId !== null) {
            $payload['validation_status_id'] = $validationStatusId;
        }

        $this->completionService->complete($task, CompleteTaskData::fromValidated($payload, $task->id), $actor);
    }

    public function uncomplete(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('complete', $task);
        $this->completionService->uncomplete($task, $actor);
    }

    public function block(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('block', $task);
        $this->actionService->block($task, $actor);
    }

    public function unblock(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('block', $task);
        $this->actionService->unblock($task, $actor);
    }

    public function priority(Task $task, int $taskPriorityId, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $this->taskService->update($task, UpdateTaskData::fromValidated([
            'task_priority_id' => $taskPriorityId,
        ]), $actor);
    }

    /**
     * Only the submitted date key(s) are touched, the same sparse-PATCH
     * shape `UpdateTaskData` already gives a single-task update — the task
     * board submits both at once (its own `dates` action), spec 0156's
     * `start_date`/`end_date` actions submit exactly one.
     */
    public function dates(Task $task, ?string $startDate, bool $startDateSubmitted, ?string $endDate, bool $endDateSubmitted, User $actor): void
    {
        Gate::forUser($actor)->authorize('update', $task);

        $payload = [];

        if ($startDateSubmitted) {
            $payload['start_date'] = $startDate;
        }

        if ($endDateSubmitted) {
            $payload['end_date'] = $endDate;
        }

        $this->taskService->update($task, UpdateTaskData::fromValidated($payload), $actor);
    }

    public function delete(Task $task, User $actor): void
    {
        Gate::forUser($actor)->authorize('delete', $task);
        $this->taskService->delete($task, $actor);
    }
}
