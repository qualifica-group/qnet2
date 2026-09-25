<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tree/hierarchical row scoping for the `tasks` domain (spec 0157, D-1):
 * root rows only ($parentId === null) or the direct children of one parent.
 * Static and stateless, split out of TasksTableDefinition purely for its
 * file-size budget (engineering.md §6) — `TasksTableDefinition::
 * applyTreeScope()` delegates here, called by `TableService::rows()` AFTER
 * every column/advanced filter and the quick search are already applied, so
 * tree mode combines with the rest of the active query (D-1: "con tutti i
 * filtri attivi") rather than replacing it.
 */
final class TaskTreeScope
{
    /**
     * @param  Builder<Task>  $query
     */
    public static function apply(Builder $query, ?int $parentId): void
    {
        $parentId === null
            ? $query->whereNull('tasks.parent_task_id')
            : $query->where('tasks.parent_task_id', $parentId);
    }
}
