<?php

declare(strict_types=1);

namespace App\Tables\PurchaseOrderStatuses;

use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue of the `purchase-order-statuses`
 * list (spec 0248). Pure data. No delete action: the rows are system rows.
 */
final class PurchaseOrderStatusColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            [
                'id' => 'name',
                'label' => 'purchaseOrderStatuses.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'color',
                'label' => 'purchaseOrderStatuses.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'icon',
                'label' => 'purchaseOrderStatuses.columns.icon',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'purchaseOrderStatuses.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
        ], ['name' => [], 'color' => EditableColumns::COLOR]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'sort_order', 'type' => 'number'],
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
                'permission' => 'purchase-order-statuses.view',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'purchase-order-statuses.viewActivity',
            ],
        ];
    }
}
