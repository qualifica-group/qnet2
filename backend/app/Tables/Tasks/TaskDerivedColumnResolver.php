<?php

declare(strict_types=1);

namespace App\Tables\Tasks;

use App\Models\Task;
use App\Services\Tasks\TaskStatusResolver;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dispatches the tasks grid's derived filter/sort to the right delegate
 * (spec 0156): `completion_percentage` has no `tasks` column behind it
 * (D-6, TaskStatusResolver), `actual_minutes`/`parent_title` are the two
 * AGGREGATE columns (D-2, TaskAggregateColumns), every other derived
 * column falls through to TaskRelationColumns — split out of
 * TasksTableDefinition purely for its file-size budget (engineering.md
 * §6).
 */
final class TaskDerivedColumnResolver
{
    public function __construct(
        private readonly TaskStatusResolver $statusResolver,
        private readonly TaskAggregateColumns $aggregateColumns,
        private readonly TaskRelationColumns $relationColumns,
    ) {}

    /**
     * @param  Builder<Task>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        if ($columnId === TaskTableConstants::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applyFilter($query, $columnConfig, $filter);

            return true;
        }

        if ($this->aggregateColumns->applyFilter($query, $columnId, $filter)) {
            return true;
        }

        return $this->relationColumns->applyFilter($query, $columnId, $filter);
    }

    /**
     * @param  Builder<Task>  $query
     */
    public function applySort(Builder $query, string $columnId, string $direction): bool
    {
        if ($columnId === TaskTableConstants::COMPLETION_PERCENTAGE_COLUMN) {
            $this->statusResolver->applySort($query, $direction);

            return true;
        }

        if ($this->aggregateColumns->applySort($query, $columnId, $direction)) {
            return true;
        }

        return $this->relationColumns->applySort($query, $columnId, $direction);
    }
}
