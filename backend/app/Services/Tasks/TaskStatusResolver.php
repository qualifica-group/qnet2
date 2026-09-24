<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
 *
 * Spec 0153, D-10: completionPercentage() is now RECURSIVE over sub-tasks —
 * 100 once the Task itself is closed; the rounded average of the children's
 * OWN percentages (recursively), capped at 99 while the Task is still open,
 * when it has sub-tasks; the plain status percentage when it has none. The
 * grid's own ORDER BY/WHERE (applySort()/applyFilter() below) deliberately
 * stay on the status' own percentage — a correlated subquery cannot express
 * a recursive aggregate, and q-net carries the identical sort/filter vs.
 * display disagreement (spec 0153 D-10's declared limit).
 */
final class TaskStatusResolver
{
    /** D-10: an open parent's percentage never rounds up to 100 on its own. */
    private const int COMPLETED_PERCENTAGE = 100;

    private const int MAX_OPEN_PERCENTAGE = 99;

    /**
     * Relations a caller must eager-load for completionPercentage() to answer
     * without a query — TasksTableDefinition::baseQuery() and
     * TaskService::DETAIL_RELATIONS both honour this. Two levels of
     * `completionSubtasks` cover the common tree; a deeper node is fetched
     * by resolveSubtasks() itself.
     *
     * @var array<int, string>
     */
    public const array EAGER_LOADS = [
        'taskStatus',
        'completionSubtasks.taskStatus',
        'completionSubtasks.completionSubtasks.taskStatus',
    ];

    private const string STATUS_TABLE = 'task_statuses';

    private const string TASKS_TABLE = 'tasks';

    private const string PERCENTAGE_COLUMN = 'completion_percentage';

    private const string STATUS_RELATION = 'taskStatus';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * The Task's completion percentage (D-10). Recurses into sub-tasks when
     * there are any; falls back to the plain status percentage otherwise
     * (defensive 0 when the status relation cannot be resolved —
     * `task_status_id` is NOT NULL and restrictOnDelete, so that branch is
     * unreachable in practice).
     */
    public function completionPercentage(Task $task): int
    {
        $status = $this->resolveStatus($task);

        // Completed (closed_positive) is always 100, as in q-net; a
        // closed_negative task keeps its configurable status percentage
        // (TaskConfig AC-044). The subtask average applies only while open.
        if ($status?->group === TaskStatusGroup::ClosedPositive) {
            return self::COMPLETED_PERCENTAGE;
        }

        if ($status?->isClosing() === true) {
            return $status->completion_percentage ?? 0;
        }

        $children = $this->resolveSubtasks($task);

        if ($children->isEmpty()) {
            return $status?->completion_percentage ?? 0;
        }

        $average = (int) round($children->avg(fn (Task $child): int => $this->completionPercentage($child)));

        return min($average, self::MAX_OPEN_PERCENTAGE);
    }

    /**
     * Reads `taskStatus` off whatever is already loaded, an EXPLICIT query
     * otherwise — never the magic `$task->taskStatus` getter on an
     * unloaded relation, which `Model::preventLazyLoading()` (on outside
     * production) would turn into a hard failure for every recursive step
     * this class takes on its own, un-eager-loaded fetches.
     */
    private function resolveStatus(Task $task): ?TaskStatus
    {
        return $task->relationLoaded(self::STATUS_RELATION) ? $task->taskStatus : $task->taskStatus()->first();
    }

    /**
     * EVERY direct child (`completionSubtasks`, never the viewer-scoped
     * `subtasks`), however it got here: eager-loaded via EAGER_LOADS, or
     * fetched here with its OWN status so the next recursive call never
     * lazy-loads either.
     *
     * @return Collection<int, Task>
     */
    private function resolveSubtasks(Task $task): Collection
    {
        if ($task->relationLoaded('completionSubtasks')) {
            return $task->completionSubtasks;
        }

        return $task->completionSubtasks()->with(self::STATUS_RELATION)->get();
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
