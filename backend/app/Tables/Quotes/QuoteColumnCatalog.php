<?php

namespace App\Tables\Quotes;

/**
 * Declarative column/filter/action catalogue for the `quotes` domain (spec
 * 0065, MT-05). Extracted out of QuotesTableDefinition (file-size split,
 * engineering.md §6): pure data (no logic).
 *
 * `code`/`title`/`created_at` are real DB columns handled entirely by the
 * generic engine. `revenue_net`/`cost_net`/`margin_net` are ALSO real DB
 * columns (the persisted header aggregates, D-9) — sortable/filterable by
 * the generic engine, no derived-column handling needed. `opportunity`/
 * `quote_status`/`commercial`/`reporter`/`supervisor` are STANDARD
 * relation-name derived columns (own FK on the quote), resolved by
 * QuotesTableDefinition/QuoteRelationColumns — all 5 sortable (a correlated
 * subquery), mirroring OpportunityColumnCatalog. `company`/`company_site`
 * (user directive 2026-07-30) join that set — `company` labelled by its
 * `denomination`, see QuoteRelationColumns — while `operational_site` is a
 * SPECIALLY-derived column (the site has no label column at all: it is
 * identified by its primary address), delegated by QuotesTableDefinition to
 * the shared OperationalSiteColumn exactly as on Opportunities.
 */
final class QuoteColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'code',
                'label' => 'quotes.columns.code',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            [
                'id' => 'title',
                'label' => 'quotes.columns.title',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                'searchable' => true,
            ],
            self::derivedColumn('opportunity', 'quotes.columns.opportunity'),
            self::derivedColumn('quote_status', 'quotes.columns.quoteStatus'),
            self::derivedColumn('commercial', 'quotes.columns.commercial'),
            self::derivedColumn('reporter', 'quotes.columns.reporter'),
            self::derivedColumn('supervisor', 'quotes.columns.supervisor'),
            [
                'id' => 'revenue_net',
                'label' => 'quotes.columns.revenueNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'cost_net',
                'label' => 'quotes.columns.costNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'margin_net',
                'label' => 'quotes.columns.marginNet',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'created_at',
                'label' => 'quotes.columns.createdAt',
                'type' => 'datetime',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
            // Appended LAST on purpose (user directive 2026-07-30): a column
            // added in the middle would shift every user's persisted column
            // layout (spec 0001); appended, it just shows up at the end.
            self::derivedColumn('company', 'quotes.columns.company'),
            self::derivedColumn('company_site', 'quotes.columns.companySite'),
            self::derivedColumn('operational_site', 'quotes.columns.operationalSite'),
        ];
    }

    /**
     * A DERIVED (related-row-label) column declaration: filterable via the
     * `set` widget and sortable (every relational column here has a
     * correlated-subquery sort).
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
                'permission' => 'quotes.view',
            ],
            [
                'key' => 'edit',
                'label' => 'actions.edit',
                'icon' => 'pencil',
                'type' => 'link',
                'confirm' => false,
                'permission' => 'quotes.update',
            ],
            [
                'key' => 'delete',
                'label' => 'actions.delete',
                'icon' => 'trash',
                'type' => 'danger',
                'confirm' => true,
                'permission' => 'quotes.delete',
            ],
            [
                'key' => 'activity',
                'label' => 'actions.activity',
                'icon' => 'history',
                'type' => 'action',
                'confirm' => false,
                'permission' => 'quotes.viewActivity',
            ],
        ];
    }
}
