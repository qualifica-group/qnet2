<?php

namespace App\Tables\Quotes;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `quotes` domain (spec 0065, MT-05).
 * Curated from the domain's own derived columns (QuoteColumnCatalog) and the
 * relations already eager-loaded by QuotesTableDefinition::baseQuery() — no
 * invented column/relation. `target` is the relation accessor name (generic
 * whereHas-by-id via AdvancedFilterApplier for every `relation` entry) or the
 * real DB column. `reporter` is deliberately NOT an advanced filter (spec
 * 0065 data_contract): it stays a plain `set` column filter only.
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
                'name' => 'quote_status',
                'label' => 'quotes.advancedFilters.quoteStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'quote-statuses'],
                'target' => 'quoteStatus',
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
                'name' => 'created_range',
                'label' => 'quotes.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
