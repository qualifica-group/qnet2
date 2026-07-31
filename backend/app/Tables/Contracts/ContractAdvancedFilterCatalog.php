<?php

declare(strict_types=1);

namespace App\Tables\Contracts;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `contracts` domain (spec 0072, MT-04),
 * mirroring QuoteAdvancedFilterCatalog. `target` is the `whereHas` dot-path
 * resolved server-side ONLY from this catalogue (never client input) — the
 * generic `AbstractTableDefinition::applyAdvancedFilter()` default already
 * handles every entry here via `AdvancedFilterApplier::applyRelation()`,
 * which matches by the related row's own primary key: Eloquent's `whereHas`
 * natively supports the multi-hop dot-paths below (`quote.opportunity`,
 * `quote.opportunity.registry`), so no override is needed on
 * ContractsTableDefinition.
 */
final class ContractAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'registry',
                'label' => 'contracts.advancedFilters.registry',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'registries'],
                'target' => 'quote.opportunity.registry',
            ],
            [
                'name' => 'opportunity',
                'label' => 'contracts.advancedFilters.opportunity',
                'type' => AdvancedFilterType::Relation,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'opportunities'],
                'target' => 'quote.opportunity',
            ],
            [
                'name' => 'commercial',
                'label' => 'contracts.advancedFilters.commercial',
                'type' => AdvancedFilterType::Relation,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'referents'],
                'target' => 'quote.commercial',
            ],
            [
                'name' => 'supervisor',
                'label' => 'contracts.advancedFilters.supervisor',
                'type' => AdvancedFilterType::Relation,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'quote.supervisor',
            ],
            [
                'name' => 'contract_status',
                'label' => 'contracts.advancedFilters.contractStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'contract-statuses'],
                'target' => 'contractStatus',
            ],
        ];
    }
}
