<?php

namespace App\Tables\RewardStatuses;

use App\Enums\AdvancedFilterType;

/**
 * Advanced-filter catalogue for the `reward-statuses` domain (spec 0060): a
 * small lookup table (RewardStatusColumnCatalog: name/description/color/
 * sort_order/is_active/created_at/updated_at) — every entry here is a
 * direct-column filter, handled entirely by the generic default (no domain
 * override needed). `color`/`description` are deliberately left out,
 * mirroring the column catalogue's own choice (color: not a meaningful
 * filter axis; description: already reachable via the basic `text` column
 * filter).
 */
final class RewardStatusAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'rewardStatuses.advancedFilters.name',
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
                'label' => 'rewardStatuses.advancedFilters.isActive',
                'type' => AdvancedFilterType::Switch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_active',
            ],
            [
                'name' => 'sort_order_range',
                'label' => 'rewardStatuses.advancedFilters.sortOrderRange',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'sort_order',
            ],
            [
                'name' => 'created_range',
                'label' => 'rewardStatuses.advancedFilters.createdRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
            [
                'name' => 'updated_range',
                'label' => 'rewardStatuses.advancedFilters.updatedRange',
                'type' => AdvancedFilterType::DateRange,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'updated_at',
            ],
        ];
    }
}
