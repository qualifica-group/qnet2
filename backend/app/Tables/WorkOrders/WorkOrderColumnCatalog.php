<?php

declare(strict_types=1);

namespace App\Tables\WorkOrders;

use App\Enums\WorkOrderType;

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
 * `SELECT DISTINCT` on. `status` and `completion_percentage` are COMPUTED
 * from the root tasks (spec 0149, resolved by WorkOrderStatusResolver):
 * `status` is `sortable: false` (no single sort key for a derived state) and
 * `set`-filterable over its 4 values; `completion_percentage` is sortable
 * but not filterable (D-10). `registry` (the Anagrafica) is DERIVED through
 * `quote.opportunity.registry` and `set`-filtered by name.
 * `contract_expiry_date` is DERIVED through `quote.contract` (the Contratto's
 * own `expiry_date`): read-only, `date`-filterable and sortable.
 *
 * Spec 0206: `title`/`type`/`callback_date`/`start_date`/`supervisors` are
 * inline-editable and write through WorkOrderCellWriter (the form's own
 * UpdateWorkOrderRequest + WorkOrderService). `is_force_closed` stays a row
 * action (it needs a reason, D-7); every other column is read-only.
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
                'editable' => true,
                // Spec 0215: a cleared title goes back to the automatic one.
                'nullable' => true,
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
                // `contracts.expiry_date`, derived through `quote.contract`;
                // edited on the contract only (WorkOrderContractExpiryColumn).
                'id' => 'contract_expiry_date',
                'label' => 'workOrders.columns.contract_expiry_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'hasFilterValues' => false,
            ],
            [
                // The client, `{id, name}` through `quote.opportunity.registry`
                // (WorkOrderRegistryColumn). Read-only: it follows the quote.
                'id' => 'registry',
                'label' => 'workOrders.columns.registry',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'type',
                'label' => 'workOrders.columns.type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                // Spec 0206: options feed the cell validator and the rich select.
                'options' => WorkOrderType::values(),
                'editable' => true,
                'nullable' => false,
            ],
            [
                'id' => 'callback_date',
                'label' => 'workOrders.columns.callback_date',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                'editable' => true,
                'nullable' => true,
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
                // Computed (spec 0149, D-5/D-10), never a real column.
                'id' => 'completion_percentage',
                'label' => 'workOrders.columns.completion_percentage',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
                'hasFilterValues' => false,
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
                'editable' => true,
                'nullable' => false,
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
                'editable' => true,
                'editor' => 'multiselect',
                'relation' => ['resource' => 'users'],
                'editableField' => 'supervisor_ids',
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
            ['columnId' => 'contract_expiry_date', 'type' => 'date'],
            ['columnId' => 'registry', 'type' => 'set'],
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
                // Spec 0193 (D-11, rev. 2026-10-06): the "€" proforma request,
                // second so it stays inline; its colour and the disabled
                // "issued" state come from the row's `proforma_status`.
                'key' => 'proforma',
                'label' => 'proformaRequests.cell.none',
                'icon' => 'euro',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'proforma-requests.create',
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
            // "Chiusura forzata" as an action, not a field (user directive
            // 2026-10-06): the client opens a reason dialog and PATCHes
            // is_force_closed/force_close_reason. `reopen` is its inverse.
            // Offered per row by WorkOrdersTableDefinition::actionsFor().
            [
                'key' => 'force_close',
                'label' => 'actions.forceClose',
                'icon' => 'lock',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'work-orders.update',
            ],
            [
                'key' => 'reopen',
                'label' => 'actions.reopen',
                'icon' => 'lock-open',
                'type' => 'action',
                'confirm' => true,
                'permission' => 'work-orders.update',
            ],
        ];
    }
}
