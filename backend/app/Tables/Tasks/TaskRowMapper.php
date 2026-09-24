<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Tasks\TaskStatusResolver;
use Illuminate\Database\Eloquent\Model;

/**
 * Task -> grid row payload (spec 0101, extended by spec 0156). Extracted out
 * of TasksTableDefinition::mapRow() (file-size split, engineering.md §6):
 * pure presentation, no query/authorization logic — `actions`/`editable` are
 * attached by the generic TableService via TasksTableDefinition's own
 * actionsFor()/authorizeUpdate() hooks, never here.
 *
 * `actual_minutes`/`parent_title` (spec 0156, D-2) are read off the two
 * SELECT aliases TaskAggregateColumns::selects() adds to baseQuery(), never
 * a second per-row query.
 */
final class TaskRowMapper
{
    public function __construct(private readonly TaskStatusResolver $statusResolver) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Task $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'registry' => $this->nameRef($row->registry),
            'task_type' => $this->badgeRef($row->taskType),
            'task_status' => $this->statusRef($row->taskStatus),
            'task_priority' => $this->badgeRef($row->taskPriority),
            'task_importance' => $this->badgeRef($row->taskImportance),
            'task_category' => $this->badgeRef($row->taskCategory),
            'start_date' => $row->start_date,
            'end_date' => $row->end_date,
            'completion_date' => $row->completion_date,
            'requester' => $this->nameRef($row->requester),
            'creator' => $this->nameRef($row->creator),
            'assignees' => $this->summarizeUsers($row->assignees->all()),
            'watchers' => $this->summarizeUsers($row->watchers->all()),
            'completion_percentage' => $this->statusResolver->completionPercentage($row),
            'estimated_minutes' => $row->estimated_minutes,
            'is_blocked' => $row->is_blocked,
            'opportunity' => $this->nameRef($row->opportunity),
            'work_order' => $row->workOrder === null
                ? null
                : ['id' => $row->workOrder->id, 'name' => $row->workOrder->title],
            'work_order_stage' => $this->nameRef($row->workOrderStage),
            'has_subtasks' => (int) $row->subtasks_count > 0,
            'is_subtask' => $row->parent_task_id !== null,
            // Spec 0156, D-2.
            'updated_at' => $row->updated_at?->toIso8601String(),
            'actual_minutes' => (int) $row->getAttribute(TaskAggregateColumns::ACTUAL_MINUTES),
            'parent_title' => $row->getAttribute(TaskAggregateColumns::PARENT_TITLE),
            'is_recurring' => $row->task_recurrence_id !== null,
            'notes_count' => (int) $row->notes_count,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function nameRef(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A configurator row with its badge attributes, so the grid renders the
     * CONFIGURED colour/icon and changing them needs no code change
     * (AC-072).
     *
     * @return array{id: int, name: string, color: string|null, icon: string|null}|null
     */
    private function badgeRef(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'color' => $related->color,
            'icon' => $related->icon,
        ];
    }

    /**
     * `task_status`'s own badgeRef(), plus its `group` (spec 0156, D-2): the
     * status PHASE the frontend needs to decide "closing transition -> open
     * the completion dialog" without re-deriving TaskStatusGroup itself.
     *
     * @return array{id: int, name: string, color: string|null, icon: string|null, group: string}|null
     */
    private function statusRef(?TaskStatus $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [...$this->badgeRef($related), 'group' => $related->group->value];
    }

    /**
     * @param  array<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeUsers(array $users): array
    {
        return array_map(
            static fn (User $user): array => ['id' => $user->id, 'name' => $user->name],
            $users,
        );
    }
}
