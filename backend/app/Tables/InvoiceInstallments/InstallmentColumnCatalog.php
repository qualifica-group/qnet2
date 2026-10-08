<?php

namespace App\Tables\InvoiceInstallments;

use App\Enums\InstallmentStatus;

/**
 * Declarative column/filter/action catalogue of the `invoice-installments`
 * domain (spec 0197). Pure data; the columns tied to a permission field are
 * mapped in FIELD_BY_COLUMN so the definition can hide them per actor.
 */
final class InstallmentColumnCatalog
{
    public const string LABEL_PREFIX = 'invoiceInstallments.columns.';

    /** The two values of the derived `overdue` column. */
    public const array OVERDUE_VALUES = ['yes', 'no'];

    /** Column id => `invoice-installments` field whose visibility gates it. */
    public const array FIELD_BY_COLUMN = [
        'amount' => 'amount',
        'collected_amount' => 'collected_amount',
        'collected_at' => 'collected_at',
        'residual_amount' => 'residual_amount',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('invoice_number_label', 'text', sortable: true, filter: 'text'),
            self::column('invoice_document_date', 'date', sortable: true, filter: 'date'),
            self::column('sequence', 'number', sortable: true),
            self::column('due_date', 'date', sortable: true, filter: 'date'),
            self::column('due_month', 'text', visible: false, filter: 'set'),
            self::column('days_overdue', 'number', sortable: true, filter: 'number'),
            self::column('status', 'badge', sortable: true, filter: 'set', options: InstallmentStatus::values()),
            self::column('overdue', 'badge', filter: 'set', options: self::OVERDUE_VALUES),
            self::column('customer', 'text', sortable: true, filter: 'set'),
            self::column('work_order', 'text', sortable: true, filter: 'set'),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('company_site', 'text', sortable: true, filter: 'set'),
            self::column('operational_site', 'text', sortable: true, filter: 'set'),
            self::column('payment_method_code', 'text', sortable: true, filter: 'set'),
            self::column('amount', 'number', sortable: true, filter: 'number'),
            self::column('collected_amount', 'number', sortable: true, filter: 'number'),
            self::column('residual_amount', 'number', sortable: true, filter: 'number'),
            self::column('collected_at', 'date', sortable: true, filter: 'date'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
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
            ['key' => 'view_invoice', 'label' => 'invoiceInstallments.actions.view_invoice', 'icon' => 'eye', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.view'],
            ['key' => 'edit', 'label' => 'invoiceInstallments.actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'invoice-installments.update'],
            ['key' => 'record_collection', 'label' => 'invoiceInstallments.actions.record_collection', 'icon' => 'hand-coins', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.collect'],
            ['key' => 'clear_collection', 'label' => 'invoiceInstallments.actions.clear_collection', 'icon' => 'undo-2', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.collect'],
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
