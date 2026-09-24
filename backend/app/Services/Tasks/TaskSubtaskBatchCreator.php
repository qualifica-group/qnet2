<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\CreateSubtaskData;
use App\Models\Task;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Creates the direct sub-tasks submitted alongside a new parent Task (spec
 * 0155, D-3), one level, INSIDE the SAME transaction
 * `App\Services\TaskService::create()` already opened for the parent: a
 * refusal on row N leaves the parent AND every row before N unsaved too,
 * since the whole call sits inside that one transaction.
 *
 * Six record-link columns are ALWAYS copied off the just-persisted $parent,
 * never taken from the per-row payload — there is no such key in
 * `CreateSubtaskData`'s shape at all: `registry_id`, `referent_id`,
 * `opportunity_id`, `work_order_id`, `lead_id`, `is_private`.
 * `work_order_stage_id` is ALWAYS null: a sub-task never sits on the
 * commessa board's own "Fase". `requester_id` is copied unconditionally too
 * (the per-row shape carries no key for it, so there is nothing to omit).
 * Five more fall back to $parent's OWN resolved value only when the row
 * OMITS the key (`CreateSubtaskData::$assigneeIds` etc. are `null` for
 * "omitted", never for "submitted empty" — see that DTO): `assignee_ids`,
 * `watcher_ids`, `task_type_id`, `task_priority_id`, `task_importance_id`.
 * `end_date` follows the same omitted/submitted split. `recurrence` never
 * applies to a sub-task and carries no key here to omit.
 *
 * The two guards a plain create runs that still make sense one level down
 * are re-asserted per row, exactly as `TaskService::create()` runs them for
 * the parent: `TaskParentDateRangeGuard::assertChildWithinParent()` (the
 * row's own dates against $parent's) and
 * `TaskWatcherOverlapGuard::assertNoOverlap()` (D-9 of spec 0118, on the
 * row's resulting sets). `TaskParentAccessGuard`/`TaskWriteLock` are not
 * re-run: $parent is the Task $creator just created inside this very
 * transaction, so `TaskAbilityResolver::canCreateSubtask()` and "not locked"
 * both hold structurally — there is no actor/state combination reaching this
 * class for which either could fail.
 */
final class TaskSubtaskBatchCreator
{
    public function __construct(
        private readonly TaskInitialStatusResolver $initialStatusResolver,
        private readonly TaskWatcherOverlapGuard $watcherOverlapGuard,
        private readonly TaskParentDateRangeGuard $parentDateRangeGuard,
        private readonly TaskDescriptionWriter $descriptionWriter,
    ) {}

    /**
     * $parentAssigneeIds/$parentWatcherIds are the exact sets
     * `TaskService::create()` just synced onto $parent's own pivots
     * (`CreateTaskData::$assigneeIds`/`$watcherIds`): read from the DTO
     * rather than off `$parent->assignees`/`$parent->watchers`, since neither
     * relation is eager-loaded on $parent at this point in the parent's own
     * write transaction (`Model::preventLazyLoading()` would fault the
     * implicit access).
     *
     * @param  array<int, CreateSubtaskData>  $subtasks
     * @param  array<int, int>  $parentAssigneeIds
     * @param  array<int, int>  $parentWatcherIds
     */
    public function createMany(
        Task $parent,
        array $subtasks,
        User $creator,
        array $parentAssigneeIds,
        array $parentWatcherIds,
    ): void {
        foreach ($subtasks as $index => $subtaskData) {
            try {
                $this->createOne($parent, $subtaskData, $index, $creator, $parentAssigneeIds, $parentWatcherIds);
            } catch (ValidationException $exception) {
                throw $this->reindexed($exception, $index);
            }
        }
    }

    /**
     * @param  array<int, int>  $parentAssigneeIds
     * @param  array<int, int>  $parentWatcherIds
     */
    private function createOne(
        Task $parent,
        CreateSubtaskData $data,
        int $position,
        User $creator,
        array $parentAssigneeIds,
        array $parentWatcherIds,
    ): void {
        $assigneeIds = $data->assigneeIds ?? $parentAssigneeIds;
        $watcherIds = $data->watcherIds ?? $parentWatcherIds;

        $task = new Task([
            'title' => $data->title,
            'parent_task_id' => $parent->id,
            'registry_id' => $parent->registry_id,
            'referent_id' => $parent->referent_id,
            'opportunity_id' => $parent->opportunity_id,
            'work_order_id' => $parent->work_order_id,
            'work_order_stage_id' => null,
            'lead_id' => $parent->lead_id,
            'is_private' => $parent->is_private,
            'task_type_id' => $data->taskTypeId ?? $parent->task_type_id,
            'task_priority_id' => $data->taskPriorityId ?? $parent->task_priority_id,
            'task_importance_id' => $data->taskImportanceId ?? $parent->task_importance_id,
            'task_category_id' => $data->taskCategoryId,
            'requester_id' => $parent->requester_id,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate ?? $parent->end_date,
            'estimated_minutes' => $data->estimatedMinutes,
            'is_blocked' => false,
        ]);

        $task->subtask_position = $position;
        $task->creator_id = $creator->id;

        $this->parentDateRangeGuard->assertChildWithinParent($task);
        $this->watcherOverlapGuard->assertNoOverlap($creator->id, $parent->requester_id, $assigneeIds, $watcherIds);

        $task->task_status_id = $this->initialStatusResolver->resolve($assigneeIds, $creator->id, $parent->requester_id);
        $task->save();

        $this->descriptionWriter->applyOnCreate($task, $data->description, $creator);

        if ($task->isDirty('description')) {
            $task->save();
        }

        $task->assignees()->sync($assigneeIds);
        $task->watchers()->sync($watcherIds);
    }

    /**
     * Rewrites a guard's plain field keys (`start_date`, `watcher_ids`, ...)
     * onto `subtasks.$index.field` (spec 0155, D-3), the shape the FormRequest's
     * own per-row rules already produce natively via Laravel's `subtasks.*`
     * wildcard — so every 422 out of this class lands in the same namespace
     * regardless of which layer caught it.
     */
    private function reindexed(ValidationException $exception, int $index): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $field => $errors) {
            $messages["subtasks.{$index}.{$field}"] = $errors;
        }

        return ValidationException::withMessages($messages);
    }
}
