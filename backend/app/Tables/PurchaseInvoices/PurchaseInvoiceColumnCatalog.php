<?php

namespace App\Tables\PurchaseInvoices;

use App\Enums\InvoicePaymentStatus;
use App\Enums\PurchaseInvoiceType;
use App\Tables\Support\EditableColumns;

/**
 * Declarative column/filter/action catalogue of the `purchase-invoices` domain
 * (spec 0250, D-10). Pure data: one row per payable document.
 */
final class PurchaseInvoiceColumnCatalog
{
    public const string LABEL_PREFIX = 'purchaseInvoices.columns.';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return EditableColumns::apply([
            self::column('document_type', 'badge', sortable: true, filter: 'set', options: PurchaseInvoiceType::values()),
            self::column('document_number', 'text', sortable: true, filter: 'text'),
            self::column('document_date', 'date', sortable: true, filter: 'date'),
            self::column('supplier', 'text', sortable: true, filter: 'set'),
            self::column('company', 'text', sortable: true, filter: 'set'),
            self::column('purchase_orders', 'text'),
            self::column('total_amount', 'number', sortable: true),
            self::column('payable_amount', 'number', sortable: true, filter: 'number'),
            self::column('paid_amount', 'number'),
            self::column('residual_amount', 'number'),
            self::column('payment_status', 'badge', options: InvoicePaymentStatus::values()),
        ], ['document_date' => []]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        $filters = [];

        foreach (self::columns() as $column) {
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
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'purchase-invoices.view'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'purchase-invoices.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-invoices.viewActivity'],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $sortable = false, ?string $filter = null, ?array $options = null): array
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

        if ($options !== null) {
            $column['options'] = $options;
        }

        return $column;
    }
}
