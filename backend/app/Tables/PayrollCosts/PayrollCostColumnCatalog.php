<?php

namespace App\Tables\PayrollCosts;

/**
 * Declarative column/filter catalogue of the `payroll-costs` table (spec 0430 D-11): a read-only grid.
 * The four computed columns are neither sortable nor filterable. Pure data.
 */
final class PayrollCostColumnCatalog
{
    public const string LABEL_PREFIX = 'payrollCosts.columns.';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('period', 'text', sortable: true, filter: 'set'),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('user', 'text', sortable: true, filter: 'text'),
            self::column('tax_code', 'text', sortable: true, filter: 'text'),
            self::column('salary_cost', 'number', sortable: true, filter: 'number'),
            self::column('contribution_cost', 'number', sortable: true, filter: 'number'),
            self::column('insurance_cost', 'number', sortable: true, filter: 'number'),
            self::column('total_cost', 'number', sortable: true, filter: 'number'),
            self::column('ordinary_hours', 'number', sortable: true, filter: 'number'),
            self::column('hired_on', 'date', sortable: true, filter: 'date'),
            self::column('terminated_on', 'date', sortable: true, filter: 'date'),
            self::column('timesheet_hours', 'number'),
            self::column('work_order_hours', 'number'),
            self::column('allocated_cost', 'number'),
            self::column('unallocated_cost', 'number'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filters = [];

        foreach (self::columns() as $column) {
            if ($column['filterable']) {
                $filters[] = ['columnId' => $column['id'], 'type' => $column['filterType']];
            }
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $sortable = false, ?string $filter = null): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => $type,
            'visible' => true,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        return $column;
    }
}
