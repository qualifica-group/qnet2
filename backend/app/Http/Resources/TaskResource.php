<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsTaskBadgeRefs;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Services\Tasks\TaskActionAvailability;
use App\Services\Tasks\TaskStatusResolver;
use App\Services\Tasks\TaskTimeEntryRequirement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TaskDetail shape (spec 0101 data_contract).
 *
 * `completion_percentage` is ALWAYS computed through TaskStatusResolver
 * (D-6), at both levels (the Task and every sub-task) — never read off a
 * column, because there is none: changing a status' own percentage moves
 * every Task in it with no write to `tasks` at all (AC-020/AC-021).
 *
 * `subtasks` lists only the children the actor may see: the relation is
 * eager-loaded already SCOPED by TaskService (AC-066), so this resource does
 * no filtering of its own and cannot drift from the query. Ordered by
 * `Task::subtasks()`'s own `subtask_position`/`id` (spec 0155, D-4). Each row
 * is a `TaskSubtaskResource` (D-5): `position` plus a `permissions.actions`
 * map computed for that CHILD, not copy-pasted from the parent's own.
 *
 * @see TaskSubtaskResource
 *
 * `open_subtasks_count` (spec 0123, D-6) is the OPPOSITE on purpose: it
 * counts every DIRECT child outside a closing phase, ignoring visibility
 * entirely (`TaskActionAvailability::openSubtasksCount()`), so the UI can
 * explain why `complete`/`approve` came back false even for an actor who
 * cannot see the child that is blocking them.
 *
 * Nothing here keys off a status LABEL (AC-024): the status ref exposes the
 * raw enum values of BOTH `system_key` (protection) and `group` (the PHASE)
 * so the client can drive behaviour off them. `group` is what the closure
 * rule branches on since the 2026-09-04 rectification of D-5, and the client
 * mirrors D-7 for UX off the PERSISTED status too — a partial PATCH that
 * changes nothing else must still be able to tell whether the task is
 * already in a closing phase, which it cannot do from `system_key` alone
 * (an ORDINARY row closes just as well now). `color`/`icon` come from the
 * configurator row so a badge changes with its configuration and not with
 * the code (AC-072).
 *
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    use FormatsTaskBadgeRefs;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resolver = app(TaskStatusResolver::class);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'registry_id' => $this->registry_id,
            'registry' => $this->nameRef($this->registry),
            'referent_id' => $this->referent_id,
            'referent' => $this->nameRef($this->referent),
            'parent_task_id' => $this->parent_task_id,
            'parent_task' => $this->parentTask === null
                ? null
                : ['id' => $this->parentTask->id, 'title' => $this->parentTask->title],
            'task_type_id' => $this->task_type_id,
            'task_type' => $this->badgeRef($this->taskType),
            'task_status_id' => $this->task_status_id,
            'task_status' => $this->statusRef(),
            'task_priority_id' => $this->task_priority_id,
            'task_priority' => $this->badgeRef($this->taskPriority),
            'task_importance_id' => $this->task_importance_id,
            'task_importance' => $this->badgeRef($this->taskImportance),
            'task_category_id' => $this->task_category_id,
            'task_category' => $this->badgeRef($this->taskCategory),
            'opportunity_id' => $this->opportunity_id,
            'opportunity' => $this->nameRef($this->opportunity),
            'lead_id' => $this->lead_id,
            'lead' => $this->leadRef(),
            'work_order_id' => $this->work_order_id,
            'work_order' => $this->workOrder === null
                ? null
                : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            // Spec 0146, D-3: null for a task outside a commessa AND for
            // every sub-task — never a stray FK a root Task's own commessa no
            // longer owns.
            'work_order_stage_id' => $this->work_order_stage_id,
            'work_order_stage' => $this->nameRef($this->workOrderStage),
            'requester_id' => $this->requester_id,
            'requester' => $this->nameRef($this->requester),
            'creator' => $this->nameRef($this->creator),
            'assignees' => $this->summarizeUsers($this->assignees),
            'watchers' => $this->summarizeUsers($this->watchers),
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'completion_date' => $this->formatDate($this->completion_date),
            'start_time' => $this->formatTime($this->start_time),
            'end_time' => $this->formatTime($this->end_time),
            'estimated_minutes' => $this->estimated_minutes,
            'is_blocked' => $this->is_blocked,
            'requires_closure_feedback' => $this->requires_closure_feedback,
            'requires_validation' => $this->requires_validation,
            // Spec 0162, D-1/D-2: whether /complete demands a segnatempo —
            // read from the SAME point CompleteTaskRequest/TaskCompletionService
            // do, so the dialog's "Registra il tempo" switch never re-derives
            // the rule.
            'requires_time_entry' => TaskTimeEntryRequirement::isRequired($this->resource),
            'closure_feedback' => $this->closure_feedback,
            'is_private' => $this->is_private,
            'completion_percentage' => $resolver->completionPercentage($this->resource),
            'recurrence' => $this->recurrenceRef(),
            'open_subtasks_count' => app(TaskActionAvailability::class)->openSubtasksCount($this->resource),
            'subtasks' => TaskSubtaskResource::collection($this->subtasks)->resolve($request),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The recurrence series this Task belongs to (spec 0120, data_contract):
     * the full rule object plus `id`, or null. `data.task_recurrence_id` is
     * DELIBERATELY not exposed on its own — the object already carries `id`,
     * and a Task's series is either fully described or not present at all.
     *
     * @return array<string, mixed>|null
     */
    private function recurrenceRef(): ?array
    {
        /** @var TaskRecurrence|null $recurrence */
        $recurrence = $this->recurrence;

        if ($recurrence === null) {
            return null;
        }

        return [
            'id' => $recurrence->id,
            'frequency' => $recurrence->frequency->value,
            'interval' => $recurrence->interval,
            'weekdays' => $recurrence->weekdays,
            'month_day' => $recurrence->month_day,
            'month_mode' => $recurrence->month_mode?->value,
            'ordinal' => $recurrence->ordinal,
            'ordinal_weekday' => $recurrence->ordinal_weekday,
            'year_month' => $recurrence->year_month,
            'workdays_only' => $recurrence->workdays_only,
            'ends' => $recurrence->ends->value,
            'ends_on' => $this->formatDate($recurrence->ends_on),
            'occurrence_count' => $recurrence->occurrence_count,
        ];
    }

    /**
     * The Lead this Task refers to (spec 0154, D-4): `{id, label}` mirroring
     * LeadForSelectResource's own `label` (a Lead has no own name column —
     * its registry's name stands in for it), or null.
     *
     * @return array{id: int, label: string}|null
     */
    private function leadRef(): ?array
    {
        $lead = $this->lead;

        return $lead === null ? null : ['id' => $lead->id, 'label' => $lead->registry?->name ?? ''];
    }

    /**
     * `H:i`, the shape the form submits (D-11) and the shape an
     * `<input type="time">` accepts. The driver decides how a TIME column
     * comes back — MySQL yields `09:30:00`, SQLite echoes whatever was
     * written — so the contract is normalized here rather than left to
     * differ between environments.
     */
    private function formatTime(?string $time): ?string
    {
        return $time === null || $time === '' ? null : substr($time, 0, 5);
    }
}
