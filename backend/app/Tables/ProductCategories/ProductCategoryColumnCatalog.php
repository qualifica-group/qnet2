<?php

namespace App\Tables\ProductCategories;

use App\Enums\CategoryManagementMode;

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
 * function name, resolved by CategoryHierarchy. `management_mode` (spec
 * 0077) is a real per-row column like `requires_quote`/`is_selectable`: its
 * static `options` list every `CategoryManagementMode` case, and it renders as
 * a `badge` (spec 0091 D-7) so the grid shows a localized pill instead of the
 * raw `single`/`multiple` value — `managementModeBadges()` below supplies the
 * metadata the generic BadgeCell needs.
 */
final class ProductCategoryColumnCatalog
{
    /** Frontend i18n namespace of the `management_mode` badge labels (`enums.<key>.<value>`). */
    public const string MANAGEMENT_MODE_ENUM_KEY = 'category_management_mode';

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
                // Card-line policy ("single"|"multiple"). Owned by the
                // branch ROOT and mirrored onto every descendant by
                // CategoryManagementModeInheritance (spec 0077), so this IS a
                // real column here: sorting/filtering need no derived-column
                // handling.
                'id' => 'management_mode',
                'label' => 'productCategories.columns.management_mode',
                'type' => 'badge',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'set',
                'options' => array_map(static fn (CategoryManagementMode $case): string => $case->value, CategoryManagementMode::cases()),
            ],
            [
                // "At most one quote per opportunity" policy. Owned by the
                // branch ROOT and mirrored onto every descendant by
                // SingleQuotePerOpportunityInheritance (user directive
                // 2026-08-07), so this IS a real column here.
                'id' => 'single_quote_per_opportunity',
                'label' => 'productCategories.columns.single_quote_per_opportunity',
                'type' => 'boolean',
                'visible' => false,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'boolean',
            ],
            [
                // Whether a positively closed offer of this branch opens a
                // contract (spec 0091). Owned by the branch ROOT and mirrored
                // onto every descendant by ContractGenerationInheritance, so
                // this IS a real column here. Visible by default: it decides
                // whether a deal ever reaches the Contratti module, which an
                // operator configuring the catalogue needs to see at a glance.
                'id' => 'generates_contract',
                'label' => 'productCategories.columns.generates_contract',
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
     * Badge metadata for the `management_mode` column (spec 0091 D-7). The
     * label is the same i18n key the frontend resolves through the enum key,
     * so the server-side fallback and the localized pill never say different
     * things; MANAGEMENT_MODE_ENUM_KEY lets the
     * cell AND the Set Filter checklist localize from
     * `enums.category_management_mode.<value>` instead of printing the raw
     * value. No icon: BadgeCell then renders a status dot in the token's
     * strong shade, which keeps the pill compact.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function managementModeBadges(): array
    {
        return [
            [
                'value' => CategoryManagementMode::Single->value,
                'label' => 'enums.category_management_mode.single',
                'color' => 'blue',
                'icon' => null,
            ],
            [
                'value' => CategoryManagementMode::Multiple->value,
                'label' => 'enums.category_management_mode.multiple',
                'color' => 'slate',
                'icon' => null,
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
            ['columnId' => 'management_mode', 'type' => 'set'],
            ['columnId' => 'single_quote_per_opportunity', 'type' => 'boolean'],
            ['columnId' => 'generates_contract', 'type' => 'boolean'],
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
