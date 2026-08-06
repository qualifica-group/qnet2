<?php

namespace App\Tables\Quotes;

use App\Enums\AdvancedFilterType;
use App\Models\QuoteWorkflowStatus;

/**
 * Advanced-filter catalogue for the `quotes` domain (spec 0065, MT-05).
 * Curated from the domain's own derived columns (QuoteColumnCatalog) and the
 * relations already eager-loaded by QuotesTableDefinition::baseQuery() — no
 * invented column/relation. `target` is the relation accessor name (generic
 * whereHas-by-id via AdvancedFilterApplier for every `relation` entry) or the
 * real DB column. `reporter` is deliberately NOT an advanced filter (spec
 * 0065 data_contract): it stays a plain `set` column filter only.
 *
 * `quote_workflow_status` (spec 0083, D-1/D-6) is deliberately NOT a
 * `relation` filter: no `quote-workflow-statuses/for-select` route exists
 * (mirroring RequestAdvancedFilterCatalog's own `workflow_status`
 * precedent) to feed an id-based AsyncPaginatedSelect. Instead it is a
 * `multiselect` SET filter over the distinct workflow-status NAMES (queried
 * at catalog-build time); the server-side apply is overridden in
 * QuotesTableDefinition::applyAdvancedFilter() to
 * `whereHas('quoteWorkflowStatus', whereIn('name', ...))`, never id-based.
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
                'name' => 'quote_workflow_status',
                'label' => 'quotes.advancedFilters.quoteWorkflowStatus',
                'type' => AdvancedFilterType::Multiselect,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'options' => self::workflowStatusOptions(),
                'target' => 'quote_workflow_status',
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

    /**
     * Distinct workflow-status names, across every workflow (global set +
     * per-workflow overrides) — the same `{value, label}` shape a static
     * enum-backed `multiselect` uses elsewhere in the codebase, but sourced
     * from the DB since these are configured lookup rows, not a PHP enum.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function workflowStatusOptions(): array
    {
        return QuoteWorkflowStatus::query()
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (string $name): array => ['value' => $name, 'label' => $name])
            ->all();
    }
}
