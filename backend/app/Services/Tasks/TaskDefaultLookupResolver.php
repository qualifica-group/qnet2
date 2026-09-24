<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use Illuminate\Database\Eloquent\Builder;

/**
 * D-8 of spec 0154: when `task_type_id`/`task_priority_id`/
 * `task_importance_id` is omitted on create, the server falls back to
 * whichever row of that lookup carries `is_default` (null when the table
 * has none set — the fallback is best-effort, never a 422). Read-only: the
 * three lookup MODELS themselves (including their own `is_default` write
 * path and the "at most one" invariant) belong to the sibling lane that
 * owns `App\Models\TaskType`/`TaskPriority`/`TaskImportance` — this class
 * only ever queries the column, never sets it.
 */
final class TaskDefaultLookupResolver
{
    public function taskTypeId(): ?int
    {
        return $this->defaultId(TaskType::query());
    }

    public function taskPriorityId(): ?int
    {
        return $this->defaultId(TaskPriority::query());
    }

    public function taskImportanceId(): ?int
    {
        return $this->defaultId(TaskImportance::query());
    }

    /**
     * @param  Builder<TaskType|TaskPriority|TaskImportance>  $query
     */
    private function defaultId(Builder $query): ?int
    {
        $id = $query->where('is_default', true)->value('id');

        return $id === null ? null : (int) $id;
    }
}
