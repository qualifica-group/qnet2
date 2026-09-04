<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Models\Task;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The SINGLE point of computation for a Task's completion percentage (spec
 * 0101, D-6): `tasks` carries NO such column — the value is a projection of
 * the Task's own status row, read at response time. Owns the badge value
 * (completionPercentage()), the grid's ORDER BY (applySort()) and the grid's
 * WHERE (applyFilter()) on the SAME class, so the three can never disagree
 * (AC-020/AC-021/AC-022), mirroring WorkOrderStatusResolver.
 *
 * Sorting goes through a CORRELATED SUBQUERY on
 * `task_statuses.completion_percentage`, never a row-multiplying JOIN and
 * never a raw ORDER BY fragment fed from input: the only two identifiers
 * reaching the SQL are the private constants below (AC-073, backend.md §8).
 * Filtering
 * delegates to the generic FilterApplier inside a `whereHas` closure, so the
 * derived column supports the exact operator set any real numeric column
 * does, without a second implementation.
 *
 * Nothing here reads a status LABEL (AC-024): the percentage lives on the
 * configurator row, so renaming a status changes nothing.
 */
final class TaskStatusResolver
{
    /**
     * Relations a caller must eager-load for completionPercentage() to answer
     * without a query — TasksTableDefinition::baseQuery() and
     * TaskService::DETAIL_RELATIONS both honour this.
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = ['taskStatus'];

    private const string STATUS_TABLE = 'task_statuses';

    private const string TASKS_TABLE = 'tasks';

    private const string PERCENTAGE_COLUMN = 'completion_percentage';

    private const string STATUS_RELATION = 'taskStatus';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * The Task's completion percentage, always read off its status row.
     * Defensive 0 when the relation cannot be resolved: `task_status_id` is
     * NOT NULL and restrictOnDelete, so this is unreachable in practice.
     */
    public function completionPercentage(Task $task): int
    {
        return $task->taskStatus?->completion_percentage ?? 0;
    }

    /**
     * ORDER BY the status' own percentage. $direction reaches the query
     * builder, which accepts only `asc`/`desc` and throws otherwise — the
     * generic engine already resolves it from its own whitelist.
     *
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->percentageSubquery(), $direction);
    }

    /**
     * The grid's filter on the derived column: the generic numeric operator
     * set, applied to `task_statuses.completion_percentage` inside a
     * `whereHas` on the status relation. Values stay bound parameters.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $columnConfig, array $filter): void
    {
        $query->whereHas(self::STATUS_RELATION, function (Builder $statusQuery) use ($columnConfig, $filter): void {
            $this->filterApplier->apply($statusQuery, self::PERCENTAGE_COLUMN, $columnConfig, $filter);
        });
    }

    private function percentageSubquery(): QueryBuilder
    {
        return DB::table(self::STATUS_TABLE)
            ->select(self::PERCENTAGE_COLUMN)
            ->whereColumn(self::STATUS_TABLE.'.id', self::TASKS_TABLE.'.task_status_id')
            ->limit(1);
    }
}
