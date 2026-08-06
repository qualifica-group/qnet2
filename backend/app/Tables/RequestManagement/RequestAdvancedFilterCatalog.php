<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `request-management` domain (spec 0049).
 * Curated from the domain's own derived columns (RequestColumnCatalog) and
 * the relations already eager-loaded by
 * RequestManagementTableDefinition::baseQuery() — no invented column/
 * relation, mirroring OpportunityAdvancedFilterCatalog. `target` is the
 * relation accessor name (generic whereHas-by-id via AdvancedFilterApplier
 * for every `relation` entry) or the real DB column (`expected_close_date`).
 *
 * Spec 0083, D-2: `workflow_status` is REMOVED — the Opportunity resolves no
 * working state of its own any more.
 *
 * `operational_site` is a PICKER, not free text (user directive 2026-07-31):
 * an id-based `relation` filter over the `operational-sites/for-select` route
 * — the same source the column's inline editor already uses — applied by the
 * generic whereHas-by-id default. The site having no own `name` column only
 * rules out a name-based whereIn, not an id-based one: the for-select route
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
                'target' => 'registry',
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
                'target' => 'referent',
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
                'name' => 'expected_close_range',
                'label' => 'requestManagement.advancedFilters.expectedCloseRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'expected_close_date',
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
