<?php

namespace App\Tables\QuoteStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `quote-statuses` domain (spec 0065): a
 * plain clone of OpportunityStatusAdvancedFilterCatalog. A small lookup
 * table (QuoteStatusColumnCatalog: name/color/sort_order/group/created_at)
 * — every entry here is a direct-column filter, handled entirely by the
 * generic default (no domain override needed). `color` is deliberately left
 * out, mirroring the column catalogue's own choice to keep it neither
 * sortable nor filterable (a swatch value, not a meaningful filter axis).
 * `group` is ALSO left out here: it is already reachable via the basic
 * `set` column filter (QuoteStatusColumnCatalog), and no advanced-filter
 * widget type in this catalogue's repertoire (AdvancedFilterType) carries a
 * static options list end-to-end.
 */
final class QuoteStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'quoteStatuses.advancedFilters.name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'sort_order_range',
                'label' => 'quoteStatuses.advancedFilters.sortOrderRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'sort_order',
            ],
            [
                'name' => 'created_range',
                'label' => 'quoteStatuses.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
