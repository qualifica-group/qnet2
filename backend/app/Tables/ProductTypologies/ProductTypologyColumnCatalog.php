<?php

namespace App\Tables\ProductTypologies;

/**
 * Declarative column/filter/action catalogue for the `product-typologies`
 * domain (spec 0099). Extracted out of ProductTypologiesTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic), mirroring
 * UnitOfMeasureColumnCatalog. Every column (name/code/description/
 * created_at/updated_at) is a real DB column handled entirely by the generic
 * engine, which is what gives the module search, filters, sorting and export
 * with no bespoke code.
 */
final class ProductTypologyColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'productTypologies.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                'id' => 'code',
                'label' => 'productTypologies.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'productTypologies.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'color',
                'label' => 'productTypologies.columns.color',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'supplier_commission_enabled',
                'label' => 'productTypologies.columns.supplier_commission_enabled',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                // Badge driven by SupplierCommissionDirection (form_enums key
                // `supplier_commission_direction`).
                'id' => 'supplier_commission_direction',
                'label' => 'productTypologies.columns.supplier_commission_direction',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'productTypologies.columns.created_at',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            [
                'id' => 'updated_at',
                'label' => 'productTypologies.columns.updated_at',
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
            ['columnId' => 'code', 'type' => 'text'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'supplier_commission_enabled', 'type' => 'boolean'],
            ['columnId' => 'supplier_commission_direction', 'type' => 'set'],
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
                'permission' => 'product-typologies.view',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'product-typologies.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'product-typologies.viewActivity',
            ],
        ];
    }
}
