<?php

namespace App\Tables\PurchaseInvoiceInstallments;

use App\Enums\PurchaseInstallmentStatus;
use App\Enums\PurchaseInvoiceType;

/**
 * Declarative column/filter/action catalogue of the `purchase-invoice-installments`
 * domain (spec 0211). Pure data; the columns tied to a permission field are
 * mapped in FIELD_BY_COLUMN so the definition can hide them per actor.
 */
final class PurchaseInstallmentColumnCatalog
{
    public const string LABEL_PREFIX = 'purchaseInvoiceInstallments.columns.';

    /** The two values of the derived `overdue` column. */
    public const array OVERDUE_VALUES = ['yes', 'no'];

    /** Column id => `purchase-invoice-installments` field whose visibility gates it. */
    public const array FIELD_BY_COLUMN = [
        'amount' => 'amount',
        'paid_amount' => 'paid_amount',
        'paid_at' => 'paid_at',
        'residual_amount' => 'residual_amount',
        'financial_account' => 'financial_account',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('document_type', 'badge', sortable: true, filter: 'set', options: PurchaseInvoiceType::values()),
            self::column('document_number', 'text', sortable: true, filter: 'text'),
            self::column('document_date', 'date', sortable: true, filter: 'date'),
            self::column('purchase_orders', 'text', filter: 'text'),
            self::column('sequence', 'number', sortable: true),
            self::column('due_date', 'date', sortable: true, filter: 'date'),
            self::column('due_month', 'text', visible: false, filter: 'set'),
            self::column('days_overdue', 'number', sortable: true, filter: 'number'),
            self::column('status', 'badge', sortable: true, filter: 'set', options: PurchaseInstallmentStatus::values()),
            self::column('overdue', 'badge', filter: 'set', options: self::OVERDUE_VALUES),
            self::column('supplier', 'text', sortable: true, filter: 'set'),
            self::column('cost_type', 'badge', sortable: true, filter: 'set'),
            self::column('work_order', 'text', sortable: true, filter: 'set'),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('company_site', 'text', sortable: true, filter: 'set'),
            self::column('operational_site', 'text', sortable: true, filter: 'set'),
            self::column('payment_method_code', 'text', sortable: true, filter: 'set'),
            self::column('amount', 'number', sortable: true, filter: 'number'),
            self::column('paid_amount', 'number', sortable: true, filter: 'number'),
            self::column('residual_amount', 'number', sortable: true, filter: 'number'),
            self::column('paid_at', 'date', sortable: true, filter: 'date'),
            self::column('financial_account', 'text', sortable: true, filter: 'set'),
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
            ['key' => 'edit', 'label' => 'purchaseInvoiceInstallments.actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-invoice-installments.update'],
            ['key' => 'record_payment', 'label' => 'purchaseInvoiceInstallments.actions.record_payment', 'icon' => 'hand-coins', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-invoices.pay'],
            ['key' => 'clear_payment', 'label' => 'purchaseInvoiceInstallments.actions.clear_payment', 'icon' => 'undo-2', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-invoices.pay'],
            ['key' => 'open_document', 'label' => 'purchaseInvoiceInstallments.actions.open_document', 'icon' => 'file-text', 'type' => 'link', 'confirm' => false, 'permission' => 'purchase-invoices.view'],
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
