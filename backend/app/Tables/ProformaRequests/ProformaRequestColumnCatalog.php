<?php

namespace App\Tables\ProformaRequests;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;

/**
 * Declarative column/filter/action catalogue for the `proforma-requests`
 * domain (spec 0193). Pure data. `kind`/`status`/`note`/`created_at` are real
 * columns handled by the generic engine; `work_order_code` is sortable through
 * a derived sort, and the other relation-backed columns are display only.
 */
final class ProformaRequestColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'work_order_code',
                'label' => 'proformaRequests.columns.work_order_code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'work_order_title',
                'label' => 'proformaRequests.columns.work_order_title',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'company',
                'label' => 'proformaRequests.columns.company',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'kind',
                'label' => 'proformaRequests.columns.kind',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => ProformaRequestKind::values(),
            ],
            [
                'id' => 'supplier',
                'label' => 'proformaRequests.columns.supplier',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'payment_method',
                'label' => 'proformaRequests.columns.payment_method',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'status',
                'label' => 'proformaRequests.columns.status',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => ProformaRequestStatus::values(),
            ],
            [
                'id' => 'note',
                'label' => 'proformaRequests.columns.note',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'assigned_to',
                'label' => 'proformaRequests.columns.assigned_to',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'assigned_by',
                'label' => 'proformaRequests.columns.assigned_by',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'created_at',
                'label' => 'proformaRequests.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'kind', 'type' => 'set', 'options' => ProformaRequestKind::values()],
            ['columnId' => 'status', 'type' => 'set', 'options' => ProformaRequestStatus::values()],
            ['columnId' => 'note', 'type' => 'text'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'proforma-requests.view'],
            ['key' => 'update', 'label' => 'actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'proforma-requests.update'],
            ['key' => 'notes', 'label' => 'actions.notes', 'icon' => 'messages-square', 'type' => 'action', 'confirm' => false, 'permission' => 'proforma-requests.view'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'proforma-requests.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'proforma-requests.viewActivity'],
        ];
    }
}
