<?php

declare(strict_types=1);

namespace App\Tables\Opportunities\Concerns;

use App\Tables\Opportunities\OpportunitySystemStatusColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The figures of the system status tabs of the Opportunita' page (spec 0409),
 * out of OpportunitiesTableDefinition (file-size limit, engineering.md §6).
 * The host injects OpportunitySystemStatusColumn as `$systemStatusColumn`.
 * The tab filter itself is ignored, so every figure stays visible whichever
 * tab is active.
 */
trait HasOpportunitySystemStatusTabs
{
    /**
     * @param  Builder<Model>  $query
     * @return array<string, int>
     */
    public function aggregates(Builder $query): array
    {
        return $this->systemStatusColumn->counts($query);
    }

    /**
     * @return array<int, string>
     */
    public function filtersIgnoredByAggregates(): array
    {
        return [OpportunitySystemStatusColumn::COLUMN_ID];
    }
}
