<?php

namespace App\Tables\ProductCategories;

/**
 * Declarative column/filter/action catalogue for the `product-categories`
 * domain (spec 0017 REV). Extracted out of ProductCategoriesTableDefinition
 * (file-size split, engineering.md §6): pure data (no logic), mirroring
 * ProductColumnCatalog.
 *
 * `name`/`description`/`created_at` are real DB columns handled entirely by
 * the generic engine. `parent` has no real DB column of its own (it is the
 * related parent category's name) and is DERIVED, mirroring
 * BusinessFunctionsTableDefinition's `manager` column. `attributes_count`/
 * `products_count` are AGGREGATE columns (withCount(), no real DB column
 * either) — sortable generically (ORDER BY sees the withCount alias), but
 * filtering/distinct-values are delegated to ProductCategoryCountColumn
 * (a raw WHERE on the alias is not portable — MySQL cannot see a SELECT-list
 * alias from WHERE). `business_function` (spec 0023) is DERIVED and NOT
 * SORTABLE (see BusinessFunctionColumn) — the EFFECTIVE (own or inherited)
 * function name, resolved by CategoryHierarchy.
 */
final class ProductCategoryColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'productCategories.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search spans this real column (spec 0009).
                'searchable' => true,
            ],
            [
                // The parent category's name, derived from the self-referencing
                // parent() relation. Sorted via a correlated subquery, filtered
                // via whereHas (both in the definition). Root categories (no
                // parent) surface as null/empty.
                'id' => 'parent',
                'label' => 'productCategories.columns.parent',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'description',
                'label' => 'productCategories.columns.description',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'text',
            ],
            [
                // The category's EFFECTIVE (own or inherited) business
                // function name (spec 0023), resolved by CategoryHierarchy.
                // NOT SORTABLE (see BusinessFunctionColumn docblock).
                'id' => 'business_function',
                'label' => 'productCategories.columns.business_function',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                // Whether the category is quoted. Owned by the branch ROOT
                // and mirrored onto every descendant by
                // RequiresQuoteInheritance, so this IS a real column here:
                // sorting/filtering need no derived-column handling.
                'id' => 'requires_quote',
                'label' => 'productCategories.columns.requires_quote',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                // Whether the category may be picked as a classification
                // target (spec 0074). A real per-row column, never inherited:
                // the generic engine sorts and filters it with no derived
                // handling.
                'id' => 'is_selectable',
                'label' => 'productCategories.columns.is_selectable',
                'type' => 'boolean',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                // Number of attributes directly assigned to this category
                // (own assignments only — NOT the effective/inherited count),
                // via withCount('attributes'). AGGREGATE (no real DB column).
                'id' => 'attributes_count',
                'label' => 'productCategories.columns.attributes_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                // Number of products classified under this category, via
                // withCount('products'). AGGREGATE (no real DB column).
                'id' => 'products_count',
                'label' => 'productCategories.columns.products_count',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'productCategories.columns.created_at',
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
            ['columnId' => 'parent', 'type' => 'set'],
            ['columnId' => 'description', 'type' => 'text'],
            ['columnId' => 'business_function', 'type' => 'set'],
            ['columnId' => 'requires_quote', 'type' => 'boolean'],
            ['columnId' => 'is_selectable', 'type' => 'boolean'],
            ['columnId' => 'attributes_count', 'type' => 'number'],
            ['columnId' => 'products_count', 'type' => 'number'],
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
                'permission' => 'product-categories.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'product-categories.update',
            ],
            [
                'key' => 'layout',
                'label' => 'actions.layout',
                'icon' => 'layout-grid',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'product-categories.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'product-categories.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'product-categories.viewActivity',
            ],
        ];
    }
}
