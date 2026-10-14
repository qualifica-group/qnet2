<?php

namespace App\Tables\CostTypes;

use App\Enums\CostTypeNature;
use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue for the `cost-types` domain
 * (spec 0210): pure data, split out of CostTypesTableDefinition. `lines_count`
 * is an AGGREGATE column (baseQuery() `withCount`): sortable, not filterable.
 */
final class CostTypeColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            [
                'id' => 'name',
                'label' => 'costTypes.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'costTypes.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'nature',
                'label' => 'costTypes.columns.nature',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'color',
                'label' => 'costTypes.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'costTypes.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'is_active',
                'label' => 'costTypes.columns.is_active',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                'id' => 'lines_count',
                'label' => 'costTypes.columns.lines_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
        ], [
            'name' => [],
            'description' => EditableColumns::NULLABLE,
            'nature' => ['options' => CostTypeNature::values()],
            'color' => EditableColumns::COLOR,
            'is_active' => [],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'nature', 'type' => 'set'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
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
                'permission' => 'cost-types.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'cost-types.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'cost-types.viewActivity',
            ],
        ];
    }
}
