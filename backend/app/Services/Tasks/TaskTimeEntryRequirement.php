<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;

/**
 * Whether completing $task demands a segnatempo (spec 0162, D-1/D-2): the
 * SINGLE point `App\Http\Requests\Tasks\CompleteTaskRequest` (the field's
 * own required/sometimes shape) and `App\Services\Tasks\TaskCompletionService`
 * (the actual write-time guard, reused by both the single-task endpoint and
 * every bulk complete via `TaskBulkActionExecutor`) read, so the rule can
 * never drift between the request-level 422 and the real write. TaskResource
 * and TaskRowMapper read the SAME method to expose it, so the frontend never
 * re-derives it either.
 *
 * A Task without a `task_type_id`, or whose type has no explicit flag,
 * behaves exactly like a type with `requires_time_entry = true` (D-2): the
 * segnatempo stays mandatory whenever there is no tipologia opting it out.
 */
final class TaskTimeEntryRequirement
{
    public static function isRequired(Task $task): bool
    {
        return $task->taskType?->requires_time_entry ?? true;
    }
}
