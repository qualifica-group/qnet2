<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Models\WorkOrder;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `renewal_number` ("Periodo", spec 0358, D-14) column of the
 * `work-orders` grid: the highest contract renewal period among the
 * commessa's pivot lines (0 = initial period, also for a commessa without
 * lines). Read-only. The number filter is delegated to the generic
 * FilterApplier over a derived table (bound values only) and the sort is a
 * correlated subquery, never a row-multiplying JOIN.
 */
final class WorkOrderRenewalColumn
{
    public const string COLUMN = 'renewal_number';

    private const string DERIVED_TABLE = 'work_order_periods';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Row value from the eager-loaded `quoteLines` pivot (no extra query).
     */
    public function project(WorkOrder $workOrder): int
    {
        return (int) $workOrder->quoteLines->max(static fn (Model $line): int => (int) $line->pivot->renewal_number);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $columnConfig, array $filter): void
    {
        $periods = WorkOrder::query()
            ->fromSub(
                WorkOrder::query()->select('work_orders.id')->selectSub($this->periodSubquery(), self::COLUMN)->toBase(),
                self::DERIVED_TABLE,
            )
            ->select(self::DERIVED_TABLE.'.id');

        $this->filterApplier->apply($periods, self::DERIVED_TABLE.'.'.self::COLUMN, $columnConfig, $filter);

        $query->whereIn('work_orders.id', $periods);
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy($this->periodSubquery(), $direction);
    }

    private function periodSubquery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('quote_line_work_order')
            ->selectRaw('COALESCE(MAX(renewal_number), 0)')
            ->whereColumn('quote_line_work_order.work_order_id', 'work_orders.id');
    }
}
