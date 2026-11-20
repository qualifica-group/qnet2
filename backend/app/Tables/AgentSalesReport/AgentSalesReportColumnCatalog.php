<?php

declare(strict_types=1);

namespace App\Tables\AgentSalesReport;

/**
 * Declarative column catalogue of the `agent-sales-report` domain (spec 0365, D-4/D-5): the 16
 * columns of the legacy "Sommario" export in the same order, all read-only, plus the product
 * line and category (tasks 427625/427940, AgentSalesReportCategoryColumns) after the title and
 * the taxable amount net of the reporter's commission after the reporter. The real `quotes`
 * columns (`code`, `title`, `revenue_net`, dates) are handled by the generic engine; the rest
 * is derived (AgentSalesReportRegistryColumns, QuoteRelationColumns).
 */
final class AgentSalesReportColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('code', 'text', filter: 'text', searchable: true),
            self::column('quote_date', 'date', filter: 'date'),
            self::column('accepted_at', 'date', filter: 'date'),
            self::column('registry', 'text', filter: 'set'),
            self::column('title', 'text', filter: 'text', searchable: true),
            self::column('root_category', 'text', sortable: false, filter: 'set'),
            self::column('category', 'text', sortable: false, filter: 'set'),
            self::column('address', 'text', sortable: false),
            self::column('postal_code', 'text', sortable: false),
            self::column('city', 'text', filter: 'text'),
            self::column('phone', 'text', sortable: false),
            self::column('email', 'text', sortable: false),
            self::column('company_site', 'text', filter: 'set'),
            self::column('managers', 'text', sortable: false, filter: 'set'),
            self::column('quote_workflow_status', 'text', filter: 'set'),
            self::column('revenue_net', 'number', filter: 'number'),
            self::column('commercial', 'text', filter: 'set'),
            self::column('reporter', 'text', filter: 'set'),
            self::column('revenue_net_after_reporter', 'number', sortable: false),
        ];
    }

    /**
     * The only row action: open the offer (task 427625). The grid links it to `/quotes/{id}`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [[
            'key' => 'view',
            'label' => 'actions.view',
            'icon' => 'eye',
            'type' => 'link',
            'confirm' => false,
            'permission' => 'quotes.view',
        ]];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_values(array_map(
            static fn (array $column): array => ['columnId' => $column['id'], 'type' => $column['filterType']],
            array_filter(self::columns(), static fn (array $column): bool => $column['filterable']),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $sortable = true, ?string $filter = null, bool $searchable = false): array
    {
        $column = [
            'id' => $id,
            'label' => 'agentSalesReport.columns.'.lcfirst(str_replace('_', '', ucwords($id, '_'))),
            'type' => $type,
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        if ($searchable) {
            $column['searchable'] = true;
        }

        return $column;
    }
}
