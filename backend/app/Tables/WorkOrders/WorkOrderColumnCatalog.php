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
 * `SELECT DISTINCT` on. `status` is the persisted working phase (spec 0395,
 * WorkOrderWorkPhaseColumn), `set`-filterable over its 5 values;
 * `completion_percentage` is COMPUTED from the manual progress entries (spec
 * 0321, resolved by WorkOrderStatusResolver): sortable but not filterable (D-10). `registry` (the Anagrafica) is DERIVED through
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
                // MAX(pivot.renewal_number) of the commessa (spec 0358, D-14); WorkOrderRenewalColumn.
                'id' => 'renewal_number',
                'label' => 'workOrders.columns.renewal_number',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                'editable' => false,
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
                'searchable' => true,
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
                // The persisted working phase (spec 0395), behind the Commesse tabs too.
                'id' => WorkOrderWorkPhaseColumn::COLUMN,
                'label' => 'workOrders.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Computed (spec 0321, D-1), its date rides in the row as `completion_recorded_on`, never a real column.
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
            [
                // Partecipanti: same avatar stack and `set` filter as the
                // Responsabili. The cell holds a plain list of people, turned
                // into the positional `participant_slots` by WorkOrderCellWriter.
                'id' => 'participants',
                'label' => 'workOrders.columns.participants',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
                'editable' => true,
                'editor' => 'multiselect',
                'relation' => ['resource' => 'users'],
                'editableField' => 'participant_slots',
            ],
            [
                // Spec 0322: the "Stato lavorazione", a real FK column rendered as a
                // colored badge; the select editor offers only the row's own set
                // (`work_order_workflow_status_options`, WorkOrderWorkflowStatusColumn).
                'id' => 'work_order_workflow_status',
                'label' => 'workOrders.columns.work_order_workflow_status',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'editable' => true,
                'editor' => 'select',
                'editableField' => 'work_order_workflow_status_id',
                'nullable' => false,
            ],
            [
                // The products' payment statuses by typology (WorkOrderLinePaymentStatusColumn): a to-many,
                // not sortable, `set`-filtered by typology + status pair, labelled by its `badges`.
                'id' => WorkOrderLinePaymentStatusColumn::COLUMN,
                'label' => 'workOrders.columns.line_payment_statuses',
                'type' => 'badge',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
        ];
    }

    /**
     * The "€" column (spec 0407): appended by WorkOrdersTableDefinition only
     * for the actors who may raise a proforma request
     * (WorkOrderProformaStatusColumn::isVisibleTo()).
     *
     * @return array<string, mixed>
     */
    public static function proformaStatusColumn(): array
    {
        return [
            'id' => WorkOrderProformaStatusColumn::COLUMN,
            'label' => 'workOrders.columns.proforma_status',
            'type' => 'badge',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
            'width' => 72,
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
            ['columnId' => 'renewal_number', 'type' => 'number'],
            ['columnId' => 'registry', 'type' => 'set'],
            ['columnId' => 'type', 'type' => 'set'],
            ['columnId' => 'callback_date', 'type' => 'date'],
            ['columnId' => 'is_force_closed', 'type' => 'set'],
            ['columnId' => 'status', 'type' => 'set'],
            ['columnId' => 'work_order_workflow_status', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
            ['columnId' => 'updated_at', 'type' => 'date'],
            ['columnId' => 'start_date', 'type' => 'date'],
            ['columnId' => 'supervisors', 'type' => 'set'],
            ['columnId' => 'participants', 'type' => 'set'],
            ['columnId' => WorkOrderLinePaymentStatusColumn::COLUMN, 'type' => 'set'],
        ];
    }

    /**
     * The legacy `OrdersTable` "Azioni" column (spec 0405): the eye (in place
     * of the legacy "Modifica"), "Responsabili e Partecipanti" and Info on the
     * row (user directives 2026-11-16), then a menu opening the commessa on its Storico
     * Note / Task / Documenti tab (`menu`: always in the "..." menu), and
     * Elimina last in that menu (user request 2026-11-19, offered per row by
     * WorkOrdersTableDefinition::actionsFor; it also enables the bulk
     * "Elimina selezionati").
     *
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
                'key' => 'team',
                'label' => 'workOrders.actions.team.label',
                'icon' => 'contact',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'work-orders.view',
            ],
            [
                'key' => 'info',
                'label' => 'workOrders.actions.openTab.info',
                'icon' => 'info',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'work-orders.view',
            ],
            [
                'key' => 'notes',
                'label' => 'workOrders.actions.openTab.notes',
                'icon' => 'messages-square',
                'type' => 'link',
                'confirm' => false,
                'menu' => true,
                'permission' => 'work-orders.view',
                'count_field' => 'notes_count',
            ],
            [
                'key' => 'tasks',
                'label' => 'workOrders.actions.openTab.tasks',
                'icon' => 'list-checks',
                'type' => 'link',
                'confirm' => false,
                'menu' => true,
                'permission' => 'tasks.viewAny',
            ],
            [
                'key' => 'documents',
                'label' => 'workOrders.actions.openTab.documents',
                'icon' => 'paperclip',
                'type' => 'link',
                'confirm' => false,
                'menu' => true,
                'permission' => 'work-orders.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'menu' => true,
                'permission' => 'work-orders.delete',
            ],
        ];
    }
}
