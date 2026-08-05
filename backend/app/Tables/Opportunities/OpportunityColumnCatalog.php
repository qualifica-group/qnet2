<?php

namespace App\Tables\Opportunities;

use App\Tables\Shared\ProductsOfInterestColumn;

/**
 * Declarative column/filter/action catalogue for the `opportunities` domain
 * (spec 0040). Extracted out of OpportunitiesTableDefinition (file-size
 * split, engineering.md §6): pure data (no logic).
 *
 * `name`/`estimated_value`/`success_probability`/`start_date`/
 * `expected_close_date`/`created_at` are real DB columns handled entirely by
 * the generic engine. `registry`/`referent`/`commercial`/`supervisor`/
 * `source` are STANDARD relation-name derived columns (own FK on the
 * opportunity), resolved by OpportunitiesTableDefinition — all 5 sortable (a
 * correlated subquery), mirroring LeadColumnCatalog. Amendment rev.3:
 * `product_category`/`business_function` are AGGREGATED to-many columns
 * (via `productLines`) — filterable (`set`, `whereHas`) but NOT sortable (no
 * single related row to order by).
 *
 * User directive 2026-07-23: `products_of_interest` is a PIVOT
 * (belongsToMany) to-many column — filterable (`set`, `whereHas`), NOT
 * sortable, and inline-editable through the shared `multiselect` editor; its
 * whole declaration lives in App\Tables\Shared\ProductsOfInterestColumn,
 * shared with the request-management catalogue.
 *
 * Spec 0056: `operational_site` is a SPECIALLY-derived column (the site has
 * no own name — sort/filter/distinct pass through its primary address
 * `line1`, delegated to the shared App\Tables\Shared\OperationalSiteColumn,
 * never the generic name-based DERIVED_RELATIONS mechanism) — sortable AND
 * filterable (set), but deliberately NOT declared `editable` (out of scope:
 * the field is edited from the form, never inline in the grid).
 *
 * Spec 0057, D-5: `name` is now DERIVED server-side (`OPP_{id}`), never a
 * client input anywhere — NOT declared `editable` here either (the row stays
 * sortable/filterable/searchable, matching "resta visibile in sola lettura in
 * tabella"), and carries no matching field key in OpportunitiesAuthorization
 * (InlineCellEditingGuardTest AC-012 requires every editable column to have
 * one; a structurally-immutable, non-editable column has none, like
 * `operational_site` above).
 */
final class OpportunityColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'name',
                'label' => 'opportunities.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
                // Spec 0057, D-5: NOT editable — the value is derived
                // server-side (`OPP_{id}`), never a client input.
                'editable' => false,
            ],
            self::derivedColumn('registry', 'opportunities.columns.registry'),
            self::derivedColumn('referent', 'opportunities.columns.referent'),
            self::derivedColumn('commercial', 'opportunities.columns.commercial'),
            self::derivedColumn('supervisor', 'opportunities.columns.supervisor'),
            // Account managers (opportunity_user pivot, to-many), rendered as an
            // avatar stack. Not sortable (a to-many value has no single sort
            // key); filterable via whereHas on the manager's name.
            [
                'id' => 'managers',
                'label' => 'opportunities.columns.managers',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            self::derivedColumn('source', 'opportunities.columns.source'),
            self::derivedColumn('operational_site', 'opportunities.columns.operationalSite'),
            OpportunityStatusColumn::declaration('opportunities.columns.status'),
            self::aggregatedColumn('product_category', 'opportunities.columns.productCategory'),
            self::aggregatedColumn('business_function', 'opportunities.columns.businessFunction'),
            ProductsOfInterestColumn::declaration('opportunities.columns.productsOfInterest'),
            [
                'id' => 'estimated_value',
                'label' => 'opportunities.columns.estimatedValue',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                // Inline cell-editing (spec 0053): real, fillable, nullable
                // column; mirrors UpdateOpportunityRequest's own bounds.
                'editable' => true,
                'nullable' => true,
                'rules' => ['min:0', 'max:9999999999999.99'],
            ],
            [
                'id' => 'success_probability',
                'label' => 'opportunities.columns.successProbability',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
                // Inline cell-editing (spec 0053): real, fillable, nullable
                // column; mirrors UpdateOpportunityRequest's own bounds.
                'editable' => true,
                'nullable' => true,
                'rules' => ['between:0,100'],
            ],
            [
                'id' => 'start_date',
                'label' => 'opportunities.columns.startDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                // Inline cell-editing (spec 0053): real, fillable, nullable
                // column, not mandatory in OpportunitiesAuthorization.
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'expected_close_date',
                'label' => 'opportunities.columns.expectedCloseDate',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
                // Inline cell-editing (spec 0053): real, fillable, nullable column.
                'editable' => true,
                'nullable' => true,
            ],
            [
                'id' => 'created_at',
                'label' => 'opportunities.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * A DERIVED (related-row-name) column declaration: filterable via the
     * `set` widget and sortable (every one of the 5 relational columns here
     * has a correlated-subquery sort), mirroring LeadColumnCatalog.
     *
     * @return array<string, mixed>
     */
    private static function derivedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => true,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * A to-many AGGREGATED (via `productLines`) column declaration: filterable
     * via `set` (whereHas on the related row's name) but never sortable — no
     * single related row to order by (amendment rev.3).
     *
     * @return array<string, mixed>
     */
    private static function aggregatedColumn(string $id, string $label): array
    {
        return [
            'id' => $id,
            'label' => $label,
            'type' => 'text',
            'visible' => true,
            'sortable' => false,
            'filterable' => true,
            'filterType' => 'set',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            self::columns(),
        );
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
                'permission' => 'opportunities.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'opportunities.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'opportunities.delete',
            ],
            [
                'key' => 'documents',
                'label' => 'actions.documents',
                'icon' => 'paperclip',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.viewDocuments',
                'count_field' => 'documents_count',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'opportunities.viewActivity',
            ],
        ];
    }
}
