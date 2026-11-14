<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderWorkPhase;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `status` column of the commesse grids and the tabs of the Commesse page:
 * the persisted legacy working phase (spec 0395, `work_orders.work_phase`),
 * filtered and counted as is. It replaced the status computed from the progress.
 */
final class WorkOrderWorkPhaseColumn
{
    public const string COLUMN = 'status';

    private const string ALL_COUNT = 'all';

    private const string QUALIFIED_COLUMN = 'work_orders.work_phase';

    public function project(WorkOrder $workOrder): ?string
    {
        return $workOrder->work_phase?->value;
    }

    /**
     * How many commesse of $query sit in each phase, plus the total under
     * `all`: the figures of the tabs.
     *
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function counts(Builder $query): array
    {
        $counts = array_fill_keys(WorkOrderWorkPhase::values(), 0);

        $totals = DB::table('work_orders')
            ->whereIn('work_orders.id', (clone $query)->reorder()->select('work_orders.id'))
            ->groupBy(self::QUALIFIED_COLUMN)
            ->selectRaw(self::QUALIFIED_COLUMN.' as phase, COUNT(*) as total')
            ->pluck('total', 'phase');

        foreach ($totals as $phase => $total) {
            if (array_key_exists((string) $phase, $counts)) {
                $counts[(string) $phase] = (int) $total;
            }
        }

        return [self::ALL_COUNT => array_sum($counts), ...$counts];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<int, string>  $values
     */
    public function applyFilter(Builder $query, array $values): void
    {
        $phases = array_values(array_filter(array_map(WorkOrderWorkPhase::tryFrom(...), $values)));

        if ($phases !== []) {
            $query->whereIn(self::QUALIFIED_COLUMN, array_column($phases, 'value'));
        }
    }
}
