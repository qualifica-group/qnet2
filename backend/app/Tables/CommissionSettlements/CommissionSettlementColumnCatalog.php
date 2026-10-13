<?php

namespace App\Tables\CommissionSettlements;

use App\Enums\CommissionSettlementStatus;

/**
 * Declarative column/filter/action catalogue of the `commission-settlements`
 * table (spec 0213). Pure data; the amount columns are tied to a permission
 * field in FIELD_BY_COLUMN so the definition can hide them per actor.
 */
final class CommissionSettlementColumnCatalog
{
    public const string LABEL_PREFIX = 'commissionSettlements.columns.';

    /** Column id => `commission-settlements` field whose visibility gates it. */
    public const array FIELD_BY_COLUMN = [
        'taxable_amount' => 'taxable_amount',
        'vat_amount' => 'vat_amount',
        'withholding_amount' => 'withholding_amount',
        'enasarco_agent_amount' => 'enasarco_agent_amount',
        'enasarco_principal_amount' => 'enasarco_principal_amount',
        'net_payable' => 'net_payable',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('code', 'text', sortable: true, filter: 'text'),
            self::column('status', 'badge', sortable: true, filter: 'set', options: CommissionSettlementStatus::values()),
            self::column('referent', 'text', sortable: true, filter: 'set'),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('period_from', 'date', sortable: true, filter: 'date'),
            self::column('period_to', 'date', sortable: true, filter: 'date'),
            self::column('period_month', 'text', visible: false, filter: 'set'),
            self::column('taxable_amount', 'number', sortable: true, filter: 'number'),
            self::column('vat_amount', 'number', sortable: true),
            self::column('withholding_amount', 'number', sortable: true),
            self::column('enasarco_agent_amount', 'number', sortable: true),
            self::column('enasarco_principal_amount', 'number', sortable: true),
            self::column('net_payable', 'number', sortable: true, filter: 'number'),
            self::column('issued_at', 'date', sortable: true, filter: 'date'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns  the columns the actor can see
     * @return array<int, array<string, mixed>>
     */
    public static function filters(array $columns): array
    {
        $filters = [];

        foreach ($columns as $column) {
            if (($column['filterable'] ?? false) !== true) {
                continue;
            }

            $filter = ['columnId' => $column['id'], 'type' => $column['filterType']];

            if (isset($column['options'])) {
                $filter['options'] = $column['options'];
            }

            $filters[] = $filter;
        }

        return $filters;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'commission-settlements.view'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'commission-settlements.viewActivity'],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null): array
    {
        $column = [
            'id' => $id,
            'label' => self::LABEL_PREFIX.$id,
            'type' => $type,
            'visible' => $visible,
            'sortable' => $sortable,
            'filterable' => $filter !== null,
        ];

        if ($filter !== null) {
            $column['filterType'] = $filter;
        }

        if ($options !== null) {
            $column['options'] = $options;
        }

        return $column;
    }
}
