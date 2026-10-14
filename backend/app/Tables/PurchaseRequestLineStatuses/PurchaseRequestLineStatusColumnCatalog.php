<?php

declare(strict_types=1);

namespace App\Tables\PurchaseRequestLineStatuses;

use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue of the
 * `purchase-request-line-statuses` list (spec 0248). Pure data; the order is
 * the display order, the list has no delete action (D-2).
 */
final class PurchaseRequestLineStatusColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            [
                'id' => 'name',
                'label' => 'purchaseRequestLineStatuses.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'color',
                'label' => 'purchaseRequestLineStatuses.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'icon',
                'label' => 'purchaseRequestLineStatuses.columns.icon',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'sort_order',
                'label' => 'purchaseRequestLineStatuses.columns.sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
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
                'permission' => 'purchase-request-line-statuses.view',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'purchase-request-line-statuses.viewActivity',
            ],
        ];
    }
}
