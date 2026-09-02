<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

/**
 * Declarative column/filter/action catalogue for the `work-orders` domain
 * (spec 0093). Extracted out of WorkOrdersTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring
 * UnitOfMeasureColumnCatalog/ContractColumnCatalog.
 *
 * `code`/`title`/`type`/`callback_date`/`is_force_closed`/`created_at`/
 * `updated_at` are real `work_orders` columns handled entirely by the
 * generic engine. `contract_number`/`quote` are DERIVED through the `quote`
 * relation (D-2, resolved by WorkOrdersTableDefinition) and declare
 * `hasFilterValues: false`: no real DB column on `work_orders` to
 * `SELECT DISTINCT` on. `status` is the ONE computed column (D-3, resolved
 * by WorkOrderStatusResolver): `sortable: false` (no single sort key for a
 * derived flag), `set`-filterable over its 2 possible values.
 */
final class WorkOrderColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'workOrders.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'title',
                'label' => 'workOrders.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                // `quotes.code`, derived through the `quote` relation (D-2).
                'id' => 'contract_number',
                'label' => 'workOrders.columns.contract_number',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                'hasFilterValues' => false,
            ],
            [
                // `quotes.title`, derived through the `quote` relation (D-2).
                'id' => 'quote',
                'label' => 'workOrders.columns.quote',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'hasFilterValues' => false,
            ],
            [
                'id' => 'type',
                'label' => 'workOrders.columns.type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'callback_date',
                'label' => 'workOrders.columns.callback_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'is_force_closed',
                'label' => 'workOrders.columns.is_force_closed',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Computed (D-3), never a real column — see class docblock.
                'id' => 'status',
                'label' => 'workOrders.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'workOrders.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'updated_at',
                'label' => 'workOrders.columns.updated_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // Spec 0096: a real NOT NULL `work_orders` column, handled by
                // the generic engine exactly like `callback_date`.
                'id' => 'start_date',
                'label' => 'workOrders.columns.start_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                // Responsabili (spec 0096, D-7): a to-many over the
                // `work_order_supervisor` pivot, rendered as an avatar stack.
                // NOT sortable — no single sort key for a to-many, exactly
                // like the Offerta's own `managers` column.
                'id' => 'supervisors',
                'label' => 'workOrders.columns.supervisors',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'title', 'type' => 'text'],
            ['columnId' => 'contract_number', 'type' => 'text'],
            ['columnId' => 'quote', 'type' => 'text'],
            ['columnId' => 'type', 'type' => 'set'],
            ['columnId' => 'callback_date', 'type' => 'date'],
            ['columnId' => 'is_force_closed', 'type' => 'set'],
            ['columnId' => 'status', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'updated_at', 'type' => 'date'],
            ['columnId' => 'start_date', 'type' => 'date'],
            ['columnId' => 'supervisors', 'type' => 'set'],
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
                'permission' => 'work-orders.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'work-orders.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'work-orders.viewActivity',
            ],
        ];
    }
}
