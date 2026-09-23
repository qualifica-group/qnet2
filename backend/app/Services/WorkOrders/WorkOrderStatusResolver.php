<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\TaskStatusGroup;
use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The SINGLE point of computation for a WorkOrder's working status and
 * completion percentage (spec 0149, D-7). Owns the badge value and the
 * percentage (progress()), the table's `status` set filter (applyFilter())
 * and the completion sort (applyCompletionSort()) on the SAME class, so the
 * four can never disagree.
 *
 * The rule (D-1..D-5) reads only the commessa's ROOT tasks
 * (`parent_task_id IS NULL`), ALL of them — never TaskVisibilityScope — and
 * ignores the cancelled ones (`closed_negative`):
 *   - `is_force_closed`                        -> closed
 *   - no root task in `closed_positive`        -> open
 *   - some completed, some still not closed    -> in_progress
 *   - some completed, none still not closed    -> completed
 * The percentage is the mean of the same tasks' `completion_percentage`.
 *
 * Every identifier reaching the SQL is a private constant or an enum value
 * bound as a parameter, never client input (backend.md §8).
 */
final class WorkOrderStatusResolver
{
    private const string TASKS_RELATION = 'tasks';

    private const string STATUS_RELATION = 'taskStatus';

    private const string TASKS_TABLE = 'tasks';

    private const string STATUS_TABLE = 'task_statuses';

    private const string WORK_ORDERS_TABLE = 'work_orders';

    private const string FORCE_CLOSED_COLUMN = 'is_force_closed';

    private const string COMPLETED_COUNT = 'completed_root_tasks_count';

    private const string UNFINISHED_COUNT = 'unfinished_root_tasks_count';

    private const string COMPLETION_AVERAGE = 'root_tasks_completion_average';

    /**
     * Adds the three root-task aggregates as correlated subqueries, so a
     * list of work orders resolves its progress without one query per row
     * (D-8). Must run before any other select is added to $query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function withProgress(Builder $query): Builder
    {
        return $query
            ->withCount([
                self::TASKS_RELATION.' as '.self::COMPLETED_COUNT => fn (Builder $tasks) => $this->constrainRootTasks($tasks, [TaskStatusGroup::ClosedPositive]),
                self::TASKS_RELATION.' as '.self::UNFINISHED_COUNT => fn (Builder $tasks) => $this->constrainRootTasks($tasks, $this->unfinishedGroups()),
            ])
            ->selectSub($this->completionAverageSubquery(), self::COMPLETION_AVERAGE);
    }

    /**
     * The status and percentage of $workOrder: read off the aggregates when
     * withProgress() already loaded them, otherwise fetched with ONE query.
     */
    public function progress(WorkOrder $workOrder): WorkOrderProgress
    {
        // Step 1: the root-task aggregates, preloaded or fetched once
        $aggregates = $this->hasAggregates($workOrder) ? $workOrder : $this->fetchAggregates($workOrder);
        $completed = (int) $aggregates->getAttribute(self::COMPLETED_COUNT);
        $unfinished = (int) $aggregates->getAttribute(self::UNFINISHED_COUNT);

        // Step 2: the status, force-close first (D-1)
        $status = match (true) {
            $workOrder->is_force_closed => WorkOrderStatus::Closed,
            $completed === 0 => WorkOrderStatus::Open,
            $unfinished > 0 => WorkOrderStatus::InProgress,
            default => WorkOrderStatus::Completed,
        };

        // Step 3: the percentage, 0 when no countable root task exists (D-5)
        $percentage = (int) round((float) $aggregates->getAttribute(self::COMPLETION_AVERAGE));

        return new WorkOrderProgress($status, $percentage);
    }

    /**
     * The `status` set filter (AC-010): matches EXACTLY the rows progress()
     * badges with one of the requested values. No value, or all four, is a
     * no-op — every row matches one of them.
     *
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        $requested = array_values(array_unique(array_intersect($values, WorkOrderStatus::values())));

        if ($requested === [] || count($requested) === count(WorkOrderStatus::cases())) {
            return;
        }

        $query->where(function (Builder $group) use ($requested): void {
            foreach ($requested as $value) {
                $group->orWhere(fn (Builder $branch) => $this->constrainToStatus($branch, WorkOrderStatus::from($value)));
            }
        });
    }

    /**
     * ORDER BY the completion percentage (AC-012), through the same
     * correlated subquery withProgress() selects. $direction reaches the
     * query builder, which accepts only `asc`/`desc`.
     *
     * @param  Builder<Model>  $query
     */
    public function applyCompletionSort(Builder $query, string $direction): void
    {
        $query->orderBy($this->completionAverageSubquery(), $direction);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function constrainToStatus(Builder $query, WorkOrderStatus $status): void
    {
        $query->where(self::WORK_ORDERS_TABLE.'.'.self::FORCE_CLOSED_COLUMN, $status === WorkOrderStatus::Closed);
        $completed = fn (Builder $tasks) => $this->constrainRootTasks($tasks, [TaskStatusGroup::ClosedPositive]);
        $unfinished = fn (Builder $tasks) => $this->constrainRootTasks($tasks, $this->unfinishedGroups());

        match ($status) {
            WorkOrderStatus::Closed => null,
            WorkOrderStatus::Open => $query->whereDoesntHave(self::TASKS_RELATION, $completed),
            WorkOrderStatus::InProgress => $query->whereHas(self::TASKS_RELATION, $completed)->whereHas(self::TASKS_RELATION, $unfinished),
            WorkOrderStatus::Completed => $query->whereHas(self::TASKS_RELATION, $completed)->whereDoesntHave(self::TASKS_RELATION, $unfinished),
        };
    }

    /**
     * Narrows a `tasks` query to the ROOT tasks whose status sits in one of
     * $groups (D-2/D-3).
     *
     * @param  Builder<Model>  $tasks
     * @param  array<int, TaskStatusGroup>  $groups
     */
    private function constrainRootTasks(Builder $tasks, array $groups): void
    {
        $tasks->whereNull(self::TASKS_TABLE.'.parent_task_id')
            ->whereHas(self::STATUS_RELATION, static function (Builder $status) use ($groups): void {
                $status->whereIn('group', array_map(static fn (TaskStatusGroup $group): string => $group->value, $groups));
            });
    }

    /**
     * The phases of a task still to be done: every non-closing one.
     *
     * @return array<int, TaskStatusGroup>
     */
    private function unfinishedGroups(): array
    {
        return array_values(array_filter(
            TaskStatusGroup::cases(),
            static fn (TaskStatusGroup $group): bool => ! $group->isClosing(),
        ));
    }

    /**
     * AVG of `task_statuses.completion_percentage` over the root tasks that
     * are not cancelled (D-5); NULL when there is none, read as 0.
     */
    private function completionAverageSubquery(): QueryBuilder
    {
        return DB::table(self::TASKS_TABLE)
            ->join(self::STATUS_TABLE, self::STATUS_TABLE.'.id', '=', self::TASKS_TABLE.'.task_status_id')
            ->whereColumn(self::TASKS_TABLE.'.work_order_id', self::WORK_ORDERS_TABLE.'.id')
            ->whereNull(self::TASKS_TABLE.'.parent_task_id')
            ->where(self::STATUS_TABLE.'.group', '!=', TaskStatusGroup::ClosedNegative->value)
            ->selectRaw('AVG('.self::STATUS_TABLE.'.completion_percentage)');
    }

    private function hasAggregates(WorkOrder $workOrder): bool
    {
        return array_key_exists(self::COMPLETED_COUNT, $workOrder->getAttributes());
    }

    private function fetchAggregates(WorkOrder $workOrder): WorkOrder
    {
        return $this->withProgress(WorkOrder::query()->whereKey($workOrder->getKey()))->firstOrFail();
    }
}
