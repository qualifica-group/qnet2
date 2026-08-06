<?php

declare(strict_types=1);

namespace App\Tables\ContractStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `contract-statuses` domain (spec 0072):
 * a small lookup table (ContractStatusColumnCatalog: name/description/color/
 * sort_order/is_active/is_default/group/created_at) — every entry here is a
 * direct-column filter, handled entirely by the generic default (no domain
 * override needed). `color`/`description` are deliberately left out,
 * mirroring the column catalogue's own choice (color: not a meaningful
 * filter axis; description: already reachable via the basic `text` column
 * filter). `group` is ALSO left out here: it is already reachable via the
 * basic `set` column filter, and no advanced-filter widget type in this
 * catalogue's repertoire (AdvancedFilterType) carries a static options list
 * end-to-end (mirrors PipelineStatusAdvancedFilterCatalog).
 */
final class ContractStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'contractStatuses.advancedFilters.name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'is_active',
                'label' => 'contractStatuses.advancedFilters.isActive',
                'type' => AdvancedFilterType::Switch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_active',
            ],
            [
                'name' => 'is_default',
                'label' => 'contractStatuses.advancedFilters.isDefault',
                'type' => AdvancedFilterType::Switch,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_default',
            ],
            [
                'name' => 'sort_order_range',
                'label' => 'contractStatuses.advancedFilters.sortOrderRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'sort_order',
            ],
            [
                'name' => 'created_range',
                'label' => 'contractStatuses.advancedFilters.createdRange',
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
