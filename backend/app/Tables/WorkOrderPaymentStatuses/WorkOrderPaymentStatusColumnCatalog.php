<?php

namespace App\Tables\WorkOrderPaymentStatuses;

/**
 * Declarative column/filter/action catalogue for the `work-order-payment-statuses`
 * domain (spec 0201): pure data. Every column is a real DB column handled by the
 * generic engine; `color` is a swatch token, so not sortable/filterable.
 */
final class WorkOrderPaymentStatusColumnCatalog
{
    private const string LABEL_PREFIX = 'workOrderPaymentStatuses.columns.';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => self::LABEL_PREFIX.'name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => self::LABEL_PREFIX.'description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'color',
                'label' => self::LABEL_PREFIX.'color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            self::booleanColumn('allows_delivery'),
            [
                'id' => 'sort_order',
                'label' => self::LABEL_PREFIX.'sort_order',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            self::booleanColumn('is_active'),
            self::dateColumn('created_at'),
            self::dateColumn('updated_at'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'allows_delivery', 'type' => 'boolean'],
            ['columnId' => 'sort_order', 'type' => 'number'],
            ['columnId' => 'is_active', 'type' => 'boolean'],
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
                'permission' => 'work-order-payment-statuses.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'work-order-payment-statuses.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'work-order-payment-statuses.viewActivity',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function booleanColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => 'boolean',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'boolean',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function dateColumn(string $id): array
    {
        return [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => 'datetime',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'date',
        ];
    }
}
