<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `contract_expiry_date` column of the `work-orders` grid: the expiry date
 * of the Contratto born from the commessa's offer, reached through
 * `work_orders.quote_id` -> `contracts.quote_id` (1:1, UNIQUE). Read-only:
 * it is edited on the contract, never on the commessa, and never copied.
 *
 * A `date` filter delegated to the generic FilterApplier inside `whereHas`
 * (bound values only) and a correlated-subquery sort, never a row-multiplying
 * JOIN. `hasFilterValues: false` in the catalogue: no `work_orders` column to
 * `SELECT DISTINCT` on. Split out of WorkOrderDerivedColumns (file size,
 * engineering.md §6), like WorkOrderRegistryColumn.
 */
final class WorkOrderContractExpiryColumn
{
    public const string COLUMN = 'contract_expiry_date';

    private const string RELATION_PATH = 'quote.contract';

    private const string CONTRACT_COLUMN = 'expiry_date';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $columnConfig, array $filter): void
    {
        $query->whereHas(self::RELATION_PATH, function (Builder $contractQuery) use ($columnConfig, $filter): void {
            $this->filterApplier->apply($contractQuery, self::CONTRACT_COLUMN, $columnConfig, $filter);
        });
    }

    /**
     * @param  Builder<Model>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(
            DB::table('contracts')
                ->select('contracts.'.self::CONTRACT_COLUMN)
                ->whereColumn('contracts.quote_id', 'work_orders.quote_id')
                ->limit(1),
            $direction,
        );
    }
}
