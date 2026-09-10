<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `request-management` domain (spec 0086:
 * the row is now a `quotes` record, D-1). Curated from the domain's own
 * derived columns (RequestColumnCatalog) and the relations already
 * eager-loaded by RequestManagementTableDefinition::baseQuery() — no invented
 * column/relation, mirroring OpportunityAdvancedFilterCatalog. `target` is
 * the relation accessor name (generic whereHas-by-id via AdvancedFilterApplier
 * for every `relation` entry, dot-path nested relations supported natively
 * by Eloquent's own `whereHas()`) or the real DB column.
 *
 * `registry`/`referent` (AC-013) now target `opportunity.registry`/
 * `opportunity.referent`: neither field lives on `quotes` any more (spec
 * 0086) — only the dot-path prefix changed, the generic id-based `whereHas`
 * default is otherwise untouched.
 *
 * `next_callback_range` targets `quotes.next_callback_at`, a real column of
 * the queried table (user directive 2026-09-04), so the generic default
 * reaches it directly.
 *
 * `operational_site` is a PICKER, not free text (user directive 2026-07-31):
 * an id-based `relation` filter over the `operational-sites/for-select` route
 * — the same source the column's inline editor already uses, unchanged by
 * spec 0086 (D-6: a real FK on `quotes` itself) — applied by the generic
 * whereHas-by-id default. The site having no own `name` column only rules
 * out a name-based whereIn, not an id-based one: the for-select route
 * composes the label ("{line1} - {city}"), so the operator picks a real site
 * instead of typing a substring of its address.
 */
final class RequestAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'registry',
                'label' => 'requestManagement.advancedFilters.registry',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'registries'],
                'target' => 'opportunity.registry',
            ],
            [
                'name' => 'referent',
                'label' => 'requestManagement.advancedFilters.referent',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'opportunity.referent',
            ],
            [
                'name' => 'operational_site',
                'label' => 'requestManagement.advancedFilters.operationalSite',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'operational-sites'],
                'target' => 'operationalSite',
            ],
            [
                'name' => 'next_callback_range',
                'label' => 'requestManagement.advancedFilters.nextCallbackRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'next_callback_at',
            ],
        ];
    }
}
