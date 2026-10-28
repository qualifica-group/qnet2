<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The SINGLE point of computation for a WorkOrder's working status and
 * completion percentage (spec 0321, which supersedes the task-based rule of
 * spec 0149). Owns the badge value, the percentage and the date (progress()),
 * the table's `status` set filter (applyFilter()) and the completion sort
 * (applyCompletionSort()) on the SAME class, so the four can never disagree.
 *
 * The rule (D-1/D-2) reads only the manual progress entries
 * (`work_order_progress_entries`, cumulative and strictly increasing per
 * commessa): the percentage is the MAX of their `percentage` (0 when none),
 * the date is the `recorded_on` of that same entry (null when none). Tasks do
 * not count any more.
 *   - `is_force_closed`  -> closed (wins)
 *   - 0                  -> open
 *   - 1..99              -> in_progress
 *   - 100                -> completed
 *
 * Every identifier reaching the SQL is a private constant or an enum value
 * bound as a parameter, never client input (backend.md §8).
 */
final class WorkOrderStatusResolver
{
    private const string ENTRIES_RELATION = 'progressEntries';

    private const string ENTRIES_TABLE = 'work_order_progress_entries';

    private const string WORK_ORDERS_TABLE = 'work_orders';

    private const string FORCE_CLOSED_COLUMN = 'is_force_closed';

    private const string PERCENTAGE_COLUMN = 'percentage';

    private const string RECORDED_ON_COLUMN = 'recorded_on';

    private const string COMPLETION_PERCENTAGE = 'progress_percentage';

    private const string COMPLETION_RECORDED_ON = 'progress_recorded_on';

    private const int COMPLETE = 100;

    /**
     * Adds the current percentage and its date as correlated subqueries, so a
     * list of work orders resolves its progress without one query per row.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function withProgress(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select($query->getModel()->getTable().'.*');
        }

        return $query
            ->selectSub($this->percentageSubquery(), self::COMPLETION_PERCENTAGE)
            ->selectSub($this->recordedOnSubquery(), self::COMPLETION_RECORDED_ON);
    }

    /**
     * The status, percentage and date of $workOrder: read off the attributes
     * when withProgress() already loaded them, otherwise fetched with ONE query.
     */
    public function progress(WorkOrder $workOrder): WorkOrderProgress
    {
        // Step 1: the progress attributes, preloaded or fetched once
        $source = array_key_exists(self::COMPLETION_PERCENTAGE, $workOrder->getAttributes())
            ? $workOrder
            : $this->withProgress(WorkOrder::query()->whereKey($workOrder->getKey()))->firstOrFail();
        $percentage = (int) $source->getAttribute(self::COMPLETION_PERCENTAGE);
        $recordedOn = $source->getAttribute(self::COMPLETION_RECORDED_ON);

        // Step 2: the status, force-close first (D-2)
        $status = match (true) {
            $workOrder->is_force_closed => WorkOrderStatus::Closed,
            $percentage === 0 => WorkOrderStatus::Open,
            $percentage < self::COMPLETE => WorkOrderStatus::InProgress,
            default => WorkOrderStatus::Completed,
        };

        return new WorkOrderProgress($status, $percentage, $recordedOn === null ? null : (string) $recordedOn);
    }

    /**
     * The `status` set filter: matches EXACTLY the rows progress() badges with
     * one of the requested values. No value, or all four, is a no-op — every
     * row matches one of them.
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
     * ORDER BY the completion percentage, through the same correlated
     * subquery withProgress() selects. $direction reaches the query builder,
     * which accepts only `asc`/`desc`.
     *
     * @param  Builder<Model>  $query
     */
    public function applyCompletionSort(Builder $query, string $direction): void
    {
        $query->orderBy($this->percentageSubquery(), $direction);
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function constrainToStatus(Builder $query, WorkOrderStatus $status): void
    {
        $query->where(self::WORK_ORDERS_TABLE.'.'.self::FORCE_CLOSED_COLUMN, $status === WorkOrderStatus::Closed);
        $complete = fn (Builder $entries) => $entries->where(self::ENTRIES_TABLE.'.'.self::PERCENTAGE_COLUMN, self::COMPLETE);

        match ($status) {
            WorkOrderStatus::Closed => null,
            WorkOrderStatus::Open => $query->whereDoesntHave(self::ENTRIES_RELATION),
            WorkOrderStatus::InProgress => $query->whereHas(self::ENTRIES_RELATION)->whereDoesntHave(self::ENTRIES_RELATION, $complete),
            WorkOrderStatus::Completed => $query->whereHas(self::ENTRIES_RELATION, $complete),
        };
    }

    /**
     * MAX of the cumulative percentages, 0 when the commessa has no entry.
     */
    private function percentageSubquery(): QueryBuilder
    {
        return $this->entriesOfWorkOrder()
            ->selectRaw('COALESCE(MAX('.self::ENTRIES_TABLE.'.'.self::PERCENTAGE_COLUMN.'), 0)');
    }

    /**
     * `recorded_on` of the entry holding the MAX percentage; NULL when none.
     */
    private function recordedOnSubquery(): QueryBuilder
    {
        return $this->entriesOfWorkOrder()
            ->select(self::ENTRIES_TABLE.'.'.self::RECORDED_ON_COLUMN)
            ->orderByDesc(self::ENTRIES_TABLE.'.'.self::PERCENTAGE_COLUMN)
            ->orderByDesc(self::ENTRIES_TABLE.'.id')
            ->limit(1);
    }

    private function entriesOfWorkOrder(): QueryBuilder
    {
        return DB::table(self::ENTRIES_TABLE)
            ->whereColumn(self::ENTRIES_TABLE.'.work_order_id', self::WORK_ORDERS_TABLE.'.id');
    }
}
