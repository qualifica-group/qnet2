<?php

namespace App\Tables\Invoices;

use App\Enums\InvoicePaymentStatus;
use App\Enums\InvoiceTag;
use App\Enums\InvoiceType;

/**
 * Declarative column/filter/action catalogue for the `invoices` domain (spec
 * 0194). Pure data. `document_year`/`document_month` are hidden filter-only
 * columns: AG Grid drops filterModel keys of unknown columns, so the month
 * strip and the year selector need a real catalogue entry to reach the server.
 */
final class InvoiceColumnCatalog
{
    /** Months of the year, as the strings the month strip sends. */
    public const array MONTHS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            self::column('number_label', 'text', filter: 'text'),
            self::column('number', 'number', visible: false, sortable: true),
            self::column('document_date', 'date', sortable: true, filter: 'date'),
            self::column('type', 'badge', filter: 'set', options: InvoiceType::values()),
            self::column('external_number', 'text', filter: 'text'),
            self::column('external_date', 'date'),
            self::column('customer', 'text', sortable: true, filter: 'text'),
            self::column('company', 'text', filter: 'set'),
            self::column('payment_method', 'text', filter: 'set'),
            self::column('work_order_code', 'text'),
            self::column('quote_code', 'text'),
            self::column('net_amount', 'number'),
            self::column('vat_amount', 'number'),
            self::column('total_amount', 'number', sortable: true),
            self::column('collected_amount', 'number'),
            self::column('residual_amount', 'number'),
            self::column('payment_status', 'badge', options: InvoicePaymentStatus::values()),
            self::column(InvoiceReminderColumn::ID, 'date', sortable: true, filter: 'date'),
            self::column('tag', 'badge', filter: 'set', options: InvoiceTag::values()),
            self::column('deviation', 'number'),
            self::column('document_year', 'number', visible: false, filter: 'set'),
            self::column('document_month', 'number', visible: false, filter: 'set', options: self::MONTHS),
        ];
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
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'invoices.view'],
            ['key' => 'update', 'label' => 'actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.update'],
            ['key' => 'details', 'label' => 'invoices.actions.details', 'icon' => 'file-pen-line', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.update'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'invoices.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.viewActivity'],
            ['key' => 'pdf', 'label' => 'invoices.actions.pdf', 'icon' => 'file-down', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.view'],
            ['key' => 'email', 'label' => 'invoices.actions.email', 'icon' => 'mail', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.sendEmail'],
            ['key' => 'remind', 'label' => 'invoices.actions.remind', 'icon' => 'bell-ring', 'type' => 'action', 'confirm' => false, 'permission' => 'invoices.sendEmail'],
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
            'label' => 'invoices.columns.'.$id,
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
