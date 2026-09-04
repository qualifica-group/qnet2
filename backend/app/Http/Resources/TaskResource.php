<?php

namespace App\Http\Resources;

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskStatusResolver;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
 * no filtering of its own and cannot drift from the query.
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
            'work_order_id' => $this->work_order_id,
            'work_order' => $this->workOrder === null
                ? null
                : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
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
            'closure_feedback' => $this->closure_feedback,
            'completion_percentage' => $resolver->completionPercentage($this->resource),
            'subtasks' => $this->summarizeSubtasks($resolver),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * The Task's own status, with the two attributes the badge needs plus the
     * two the client drives behaviour off: the phase key (never the label)
     * and the percentage the whole module derives from it.
     *
     * @return array<string, mixed>|null
     */
    private function statusRef(): ?array
    {
        $status = $this->taskStatus;

        if ($status === null) {
            return null;
        }

        return [
            'id' => $status->id,
            'name' => $status->name,
            'color' => $status->color,
            'icon' => $status->icon,
            'system_key' => $status->system_key?->value,
            'group' => $status->group->value,
            'completion_percentage' => $status->completion_percentage,
        ];
    }

    /**
     * The lean sub-task rows the detail's "Sotto-task" section renders
     * (D-12). Already scoped by the eager load (AC-066).
     *
     * @return array<int, array<string, mixed>>
     */
    private function summarizeSubtasks(TaskStatusResolver $resolver): array
    {
        /** @var Collection<int, Task> $subtasks */
        $subtasks = $this->subtasks;

        return $subtasks->map(fn (Task $subtask): array => [
            'id' => $subtask->id,
            'title' => $subtask->title,
            'task_status' => $this->badgeRef($subtask->taskStatus),
            'completion_percentage' => $resolver->completionPercentage($subtask),
            'assignees' => $this->summarizeUsers($subtask->assignees),
        ])->all();
    }

    /**
     * @param  Collection<int, User>  $users
     * @return array<int, array{id: int, name: string}>
     */
    private function summarizeUsers(Collection $users): array
    {
        return $users->map(static fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
        ])->all();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function nameRef(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * A lookup configurator row projected with its badge attributes, so the
     * grid and the detail render the SAME configured colour/icon (AC-072).
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
     * `Y-m-d`, the shape the data_contract declares and the shape an
     * `<input type="date">` accepts. The `date:Y-m-d` cast alone is not
     * enough: it governs the MODEL's serialization, while a Resource hands
     * the raw CarbonImmutable to json_encode, which renders a full ISO-8601
     * timestamp (the bug spec 0096 found on WorkOrderResource).
     */
    private function formatDate(?CarbonInterface $date): ?string
    {
        return $date?->format('Y-m-d');
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
