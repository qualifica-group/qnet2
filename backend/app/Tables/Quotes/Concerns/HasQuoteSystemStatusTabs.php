<?php

declare(strict_types=1);

namespace App\Tables\Quotes\Concerns;

use App\Tables\Quotes\QuoteSystemStatusColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The figures of the system status tabs of the Offerte page (spec 0409), out
 * of QuotesTableDefinition (file-size limit, engineering.md §6): how many
 * offers of the filtered set sit on each system status, keyed by the status
 * name, the value the `quote_system_status` set filter takes. The tab filter
 * itself is ignored, so every figure stays visible whichever tab is active.
 */
trait HasQuoteSystemStatusTabs
{
    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function aggregates(Builder $query): array
    {
        return DB::table('quotes')
            ->join('quote_workflow_statuses', 'quote_workflow_statuses.id', '=', 'quotes.quote_workflow_status_id')
            ->join('quote_system_statuses', 'quote_system_statuses.id', '=', 'quote_workflow_statuses.quote_system_status_id')
            ->whereIn('quotes.id', (clone $query)->reorder()->select('quotes.id'))
            ->groupBy('quote_system_statuses.name')
            ->selectRaw('quote_system_statuses.name as status, COUNT(*) as total')
            ->pluck('total', 'status')
            ->map(static fn (mixed $total): int => (int) $total)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function filtersIgnoredByAggregates(): array
    {
        return [QuoteSystemStatusColumn::COLUMN_ID];
    }
}
