<?php

namespace App\Tables\RewardTypes;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `reward-types` domain (spec 0058): a
 * small lookup table (RewardTypeColumnCatalog: name/color/created_at/
 * updated_at) — every entry here is a direct-column filter, handled entirely
 * by the generic default (no domain override needed). `color` is
 * deliberately left out, mirroring the column catalogue's own choice to keep
 * it neither sortable nor filterable (a swatch value, not a meaningful
 * filter axis).
 */
final class RewardTypeAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'rewardTypes.advancedFilters.name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'created_range',
                'label' => 'rewardTypes.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
            [
                'name' => 'updated_range',
                'label' => 'rewardTypes.advancedFilters.updatedRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'updated_at',
            ],
        ];
    }
}
