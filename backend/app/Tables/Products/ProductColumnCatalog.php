<?php

namespace App\Tables\Products;

/**
 * Declarative column/filter/action catalogue for the `products` domain
 * (spec 0017). Extracted out of ProductsTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic), mirroring BusinessFunctionColumnCatalog.
 *
 * `name`/`description`/`cost`/`price`/`created_at` are real DB columns
 * handled entirely by the generic engine. `category` has no real column of
 * its own (it is the related category's name) and is DERIVED: its set
 * filter/sort/distinct-values are resolved by ProductsTableDefinition,
 * mirroring BusinessFunctionsTableDefinition's `manager` derived column. No
 * dynamic attribute ever appears here (spec 0017 decision — the products
 * grid shows only generic fields).
 */
final class ProductColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'products.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'description',
                'label' => 'products.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                'id' => 'cost',
                'label' => 'products.columns.cost',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'price',
                'label' => 'products.columns.price',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // The category's name, derived from the category() relation.
                // Sorted via a correlated subquery, filtered via whereHas
                // (both in the definition).
                'id' => 'category',
                'label' => 'products.columns.category',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // The product's category's EFFECTIVE (own or inherited)
                // business function name (spec 0023), read-only, resolved by
                // CategoryHierarchy. NOT SORTABLE (see BusinessFunctionColumn
                // docblock).
                'id' => 'business_function',
                'label' => 'products.columns.business_function',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // The Regione, derived from the state() relation (geo
                // reference data, localized in ProductsTableDefinition
                // mirroring ProjectsTableDefinition's GEO_COLUMN_IDS).
                'id' => 'state',
                'label' => 'products.columns.state',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Real DB column rendered as a badge (ProductType), driven by
                // config/config.php form_enums `product_type`.
                'id' => 'product_type',
                'label' => 'products.columns.product_type',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'created_at',
                'label' => 'products.columns.created_at',
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
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'cost', 'type' => 'number'],
            ['columnId' => 'price', 'type' => 'number'],
            ['columnId' => 'category', 'type' => 'set'],
            ['columnId' => 'business_function', 'type' => 'set'],
            ['columnId' => 'state', 'type' => 'set'],
            ['columnId' => 'product_type', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
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
                'permission' => 'products.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'products.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'products.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'products.viewActivity',
            ],
        ];
    }
}
