<?php

namespace App\Tables\Quotes;

use App\Enums\AdvancedFilterType;
use App\Enums\CategoryUsageArea;
use App\Tables\Shared\PresetListFilters;

/**
 * Advanced-filter catalogue for the `quotes` domain (spec 0065, MT-05).
 * Curated from the domain's own derived columns (QuoteColumnCatalog) and the
 * relations already eager-loaded by QuotesTableDefinition::baseQuery() — no
 * invented column/relation. `target` is the relation accessor name (generic
 * whereHas-by-id via AdvancedFilterApplier for every `relation` entry) or the
 * real DB column. `reporter` is deliberately NOT an advanced filter (spec
 * 0065 data_contract): it stays a plain `set` column filter only.
 *
 * `quote_system_status` (spec 0404 A-D7) is a `multiselect` SET filter over
 * the system status NAMES; the server-side apply is overridden in
 * QuotesTableDefinition::applyAdvancedFilter() (QuoteSystemStatusColumn),
 * never id-based.
 */
final class QuoteAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'opportunity',
                'label' => 'quotes.advancedFilters.opportunity',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'opportunities'],
                'target' => 'opportunity',
            ],
            [
                'name' => QuoteSystemStatusColumn::COLUMN_ID,
                'label' => 'quotes.advancedFilters.quoteSystemStatus',
                'type' => AdvancedFilterType::Multiselect,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'options' => QuoteSystemStatusColumn::nameOptions(),
                'target' => QuoteSystemStatusColumn::COLUMN_ID,
            ],
            [
                'name' => 'commercial',
                'label' => 'quotes.advancedFilters.commercial',
                'type' => AdvancedFilterType::Relation,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'commercial',
            ],
            [
                'name' => 'supervisor',
                'label' => 'quotes.advancedFilters.supervisor',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'supervisor',
            ],
            [
                'name' => 'managers',
                'label' => 'quotes.advancedFilters.managers',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'managers',
            ],
            [
                'name' => 'created_range',
                'label' => 'quotes.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
            [
                'name' => 'registry',
                'label' => 'quotes.advancedFilters.registry',
                'type' => AdvancedFilterType::Relation,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'registries'],
                'target' => 'opportunity.registry',
            ],
            PresetListFilters::productCategoryFilter('quotes', 8, CategoryUsageArea::Opportunities),
        ];
    }
}
