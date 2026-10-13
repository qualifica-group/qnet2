<?php

namespace App\Tables\CommissionSettlementRuns;

/**
 * Declarative column/filter catalogue of the `commission-settlement-runs`
 * table (spec 0213): a read-only history, so no actions. Pure data.
 */
final class CommissionSettlementRunColumnCatalog
{
    public const string LABEL_PREFIX = 'commissionSettlementRuns.columns.';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('created_at', 'date', sortable: true),
            self::column('period_from', 'date', sortable: true, filter: 'date'),
            self::column('period_to', 'date', sortable: true, filter: 'date'),
            self::column('company', 'text', filter: 'set'),
            self::column('settlements_count', 'number', sortable: true),
            self::column('skipped_count', 'number', sortable: true),
            self::column('created_by', 'text', sortable: true, filter: 'set'),
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
