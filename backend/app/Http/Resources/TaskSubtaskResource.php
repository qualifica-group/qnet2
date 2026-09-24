<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Authorization\TasksAuthorization;
use App\Http\Resources\Concerns\FormatsTaskBadgeRefs;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The lean sub-task row TaskResource's own "Sotto-task" section renders
 * (D-12), extended by spec 0155 D-5 with `position` (`subtask_position`,
 * `App\Models\Task::subtasks()`'s own sort key) and `permissions.actions` —
 * the SAME map shape `App\Authorization\ResourcePermissionsBuilder` puts
 * under the parent's top-level `permissions.actions`, computed here for the
 * CHILD via `TasksAuthorization::actionPermissions()` so the detail's panel
 * can drive its own reorder/delete/complete controls off the child's own
 * matrix row rather than the parent's.
 *
 * Reused verbatim by `TaskSubtaskReorderController` (spec 0155, contract:
 * `POST .../subtasks/reorder` -> `{ data: subtasks[] }`), so the two
 * endpoints can never drift on this shape.
 *
 * @mixin Task
 */
class TaskSubtaskResource extends JsonResource
{
    use FormatsTaskBadgeRefs;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $actor */
        $actor = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'task_status' => $this->badgeRef($this->taskStatus),
            'completion_percentage' => app(TaskStatusResolver::class)->completionPercentage($this->resource),
            'assignees' => $this->summarizeUsers($this->assignees),
            'position' => $this->subtask_position,
            'permissions' => [
                'actions' => app(TasksAuthorization::class)->actionPermissions($actor, $this->resource),
            ],
        ];
    }
}
