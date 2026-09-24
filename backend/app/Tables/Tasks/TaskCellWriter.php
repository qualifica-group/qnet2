<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\DataObjects\Tasks\UpdateTaskData;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;

/**
 * The write behind the tasks grid's inline cell edit (spec 0156, D-8):
 * TasksTableDefinition::updateCell() delegates here instead of the generic
 * `$row->update([...])` (ResolvesEditableColumns's own default), because a
 * Task write must run through EVERY guard `App\Services\TaskService::update()`
 * already enforces for a single-task PATCH (TaskWriteLock, TaskManualStatusGuard,
 * the "Fase" guard, the watcher-overlap rule, the notification map of 0153
 * D-13...) — never a raw Eloquent update that would bypass all of them.
 *
 * `$fieldKey` reaching this class is already the WRITE column
 * (TableCellUpdateService resolves `editableField` before calling
 * `updateCell()`), and every one of the eleven editable fields of D-8 has an
 * IDENTICALLY-named property on `UpdateTaskData::fromValidated()` — a single
 * `[$fieldKey => $value]` payload is therefore a genuine, if minimal, sparse
 * PATCH: exactly what `TaskService::update()` already knows how to apply, no
 * translation layer needed.
 */
final class TaskCellWriter
{
    public function __construct(private readonly TaskService $service) {}

    public function write(Task $task, string $fieldKey, mixed $value, User $actor): Task
    {
        return $this->service->update($task, UpdateTaskData::fromValidated([$fieldKey => $value]), $actor);
    }
}
