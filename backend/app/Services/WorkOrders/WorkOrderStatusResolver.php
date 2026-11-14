<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * The SINGLE point of computation for a WorkOrder's completion percentage
 * (spec 0321, which supersedes the task-based rule of spec 0149): the
 * percentage and its date (progress()) and the completion sort
 * (applyCompletionSort()) on the SAME class, so they can never disagree. The
 * status it used to derive from the percentage is now the persisted working
 * phase (spec 0395, `work_orders.work_phase`).
 *
 * The rule (D-1) reads only the manual progress entries
 * (`work_order_progress_entries`, each holding an INCREMENT, like the
 * legacy `avanzamento_commesse`): the percentage is the SUM of their
 * `percentage` (0 when none; migrated legacy data may exceed 100), the date is
 * the MAX of their `recorded_on` (null when none). Tasks do not count.
 *
 * Every identifier reaching the SQL is a private constant, never client input (backend.md §8).
 */
final class WorkOrderStatusResolver
{
    private const string ENTRIES_TABLE = 'work_order_progress_entries';

    private const string WORK_ORDERS_TABLE = 'work_orders';

    private const string PERCENTAGE_COLUMN = 'percentage';

    private const string RECORDED_ON_COLUMN = 'recorded_on';

    private const string COMPLETION_PERCENTAGE = 'progress_percentage';

    private const string COMPLETION_RECORDED_ON = 'progress_recorded_on';

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
     * The percentage and date of $workOrder: read off the attributes
     * when withProgress() already loaded them, otherwise fetched with ONE query.
     */
    public function progress(WorkOrder $workOrder): WorkOrderProgress
    {
        $source = array_key_exists(self::COMPLETION_PERCENTAGE, $workOrder->getAttributes())
            ? $workOrder
            : $this->withProgress(WorkOrder::query()->whereKey($workOrder->getKey()))->firstOrFail();
        $percentage = (int) $source->getAttribute(self::COMPLETION_PERCENTAGE);
        $recordedOn = $source->getAttribute(self::COMPLETION_RECORDED_ON);

        return new WorkOrderProgress($percentage, $recordedOn === null ? null : (string) $recordedOn);
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
     * SUM of the increments, 0 when the commessa has no entry.
     */
    private function percentageSubquery(): QueryBuilder
    {
        return $this->entriesOfWorkOrder()
            ->selectRaw('COALESCE(SUM('.self::ENTRIES_TABLE.'.'.self::PERCENTAGE_COLUMN.'), 0)');
    }

    /**
     * Latest `recorded_on`; NULL when none.
     */
    private function recordedOnSubquery(): QueryBuilder
    {
        return $this->entriesOfWorkOrder()
            ->selectRaw('MAX('.self::ENTRIES_TABLE.'.'.self::RECORDED_ON_COLUMN.')');
    }

    private function entriesOfWorkOrder(): QueryBuilder
    {
        return DB::table(self::ENTRIES_TABLE)
            ->whereColumn(self::ENTRIES_TABLE.'.work_order_id', self::WORK_ORDERS_TABLE.'.id');
    }
}
