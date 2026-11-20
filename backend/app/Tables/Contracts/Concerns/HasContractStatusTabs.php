<?php

declare(strict_types=1);

namespace App\Tables\Contracts\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The figures of the status tabs of the Contratti page (spec 0397), out of
 * ContractsTableDefinition (file-size limit, engineering.md §6): how many
 * contracts of the filtered set sit on each contract status, keyed by the
 * status name, the value the `contract_status` set filter takes. The tab
 * filter itself is ignored, so every figure stays visible whichever tab is active.
 */
trait HasContractStatusTabs
{
    private const string STATUS_TAB_COLUMN = 'contract_status';

    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function aggregates(Builder $query): array
    {
        return DB::table('contracts')
            ->join('contract_statuses', 'contract_statuses.id', '=', 'contracts.contract_status_id')
            ->whereIn('contracts.id', (clone $query)->reorder()->select('contracts.id'))
            ->groupBy('contract_statuses.name')
            ->selectRaw('contract_statuses.name as status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function filtersIgnoredByAggregates(): array
    {
        return [self::STATUS_TAB_COLUMN];
    }
}
