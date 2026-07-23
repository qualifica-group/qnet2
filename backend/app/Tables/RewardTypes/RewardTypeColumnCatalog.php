<?php

namespace App\Tables\RewardTypes;

/**
 * Declarative column/filter/action catalogue for the `reward-types` domain
 * (spec 0058). Extracted out of RewardTypesTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring
 * OpportunityStatusColumnCatalog minus the system-row/sort_order/group layer
 * (spec 0058 precedent). `color` is deliberately not sortable/filterable (a
 * swatch value, not a meaningful ordering/filter axis). `created_at`/
 * `updated_at` are both sortable and basic-filterable, exactly like the
 * template: the column filter and the DateRange advanced filter
 * (RewardTypeAdvancedFilterCatalog) coexist, they are not alternatives.
 */
final class RewardTypeColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'rewardTypes.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'color',
                'label' => 'rewardTypes.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'rewardTypes.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'updated_at',
                'label' => 'rewardTypes.columns.updated_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'updated_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            [
                'key' => 'view',
                'label' => 'actions.view',
                'icon' => 'eye',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'reward-types.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'reward-types.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'reward-types.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'reward-types.viewActivity',
            ],
        ];
    }
}
