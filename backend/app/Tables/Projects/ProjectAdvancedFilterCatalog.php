<?php

namespace App\Tables\Projects;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `projects` domain (spec 0032). Curated
 * from ProjectColumnCatalog's own DERIVED_RELATIONS (ProjectsTableDefinition)
 * — no invented column/relation; the 4 pure-geo FKs (country/state/province/
 * city) are deliberately left out, mirroring the column catalogue's own
 * cascade-select nature (no standalone for-select resource, ADR 0010) rather
 * than the plain `{search, offset, limit, ids}` for-select standard the other
 * relations use. `target` is the relation accessor name (generic
 * whereHas-by-id via AdvancedFilterApplier for every `relation` entry — every
 * FK here is the project's OWN, no doubly-derived logic like Campaigns'
 * `pipeline_status`) or the real DB column (`total_budget`/`created_at`).
 */
final class ProjectAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'pipeline_status',
                'label' => 'projects.advancedFilters.pipelineStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'pipeline-statuses'],
                'target' => 'pipelineStatus',
            ],
            [
                'name' => 'business_function',
                'label' => 'projects.advancedFilters.businessFunction',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'business-functions'],
                // Spec 0094: NESTED dot-path (`productLines.businessFunction`,
                // no longer the project's own direct relation) —
                // AdvancedFilterApplier's `whereHas($target, ...)` call needs
                // no change: Eloquent's own `whereHas()` already supports
                // dot-path nested relations (mirrors
                // OpportunityAdvancedFilterCatalog's own amendment).
                'target' => 'productLines.businessFunction',
            ],
            [
                'name' => 'product_category',
                'label' => 'projects.advancedFilters.productCategory',
                'type' => AdvancedFilterType::Relation,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'product-categories'],
                'target' => 'productLines.productCategory',
            ],
            [
                'name' => 'partner',
                'label' => 'projects.advancedFilters.partner',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'partner',
            ],
            [
                'name' => 'budget_range',
                'label' => 'projects.advancedFilters.budgetRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'total_budget',
            ],
            [
                'name' => 'created_range',
                'label' => 'projects.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
        ];
    }
}
