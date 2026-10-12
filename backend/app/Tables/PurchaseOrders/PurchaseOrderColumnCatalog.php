<?php

namespace App\Tables\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Columns;

/**
 * Declarative column/filter/action catalogue for the `purchase-orders` domain
 * (spec 0210). Pure data. The status tabs send a `status` set filter (a real
 * column). `code` is derived ("{number}/{year}": sortable by year, number;
 * searchable); `supplier_vat_number` is a hidden, search-only column.
 */
final class PurchaseOrderColumnCatalog
{
    /** Relation columns filtered by a text "contains" on [relation, column]. */
    public const array TEXT_RELATIONS = [
        'supplier' => ['supplier', 'name'],
        'company' => ['company', 'denomination'],
        'company_site' => ['companySite', 'name'],
        'payment_method' => ['paymentMethod', 'name'],
        'customer' => ['customer', 'name'],
        'work_order' => ['workOrder', 'code'],
        'quote' => ['quote', 'title'],
        'opportunity' => ['opportunity', 'name'],
        'sectors' => ['sectors', 'name'],
        'created_by' => ['createdBy', 'name'],
    ];

    /** Sortable relation columns: id => [related table, foreign key on purchase_orders, column]. */
    public const array SORT_RELATIONS = [
        'supplier' => ['registries', 'purchase_orders.supplier_id', 'name'],
        'company' => ['companies', 'purchase_orders.company_id', 'denomination'],
        'company_site' => ['company_sites', 'purchase_orders.company_site_id', 'name'],
        'payment_method' => ['payment_methods', 'purchase_orders.payment_method_id', 'name'],
        'customer' => ['registries', 'purchase_orders.customer_id', 'name'],
        'work_order' => ['work_orders', 'purchase_orders.work_order_id', 'code'],
        'quote' => ['quotes', 'purchase_orders.quote_id', 'title'],
        'opportunity' => ['opportunities', 'purchase_orders.opportunity_id', 'name'],
        'created_by' => ['users', 'purchase_orders.created_by', 'name'],
    ];

    /** Relation columns hidden by default (the others are visible). */
    private const array HIDDEN_RELATIONS = ['company_site', 'payment_method', 'customer', 'quote', 'opportunity', 'sectors', 'created_by'];

    public const string CODE_COLUMN = 'code';

    public const string SECTORS_COLUMN = 'sectors';

    public const string SUPPLIER_COLUMN = 'supplier';

    public const string SUPPLIER_VAT_COLUMN = 'supplier_vat_number';

    private const string PREFIX = 'purchaseOrders';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        $relations = [];

        foreach (array_keys(self::TEXT_RELATIONS) as $id) {
            $relations[$id] = self::column(
                $id,
                'text',
                visible: ! in_array($id, self::HIDDEN_RELATIONS, true),
                sortable: isset(self::SORT_RELATIONS[$id]),
                filter: 'text',
                searchable: $id === self::SUPPLIER_COLUMN,
            );
        }

        return [
            self::column(self::CODE_COLUMN, 'text', sortable: true, searchable: true),
            self::column('number', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('year', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('order_date', 'date', sortable: true, filter: 'date'),
            self::column('status', 'badge', sortable: true, filter: 'set', options: PurchaseOrderStatus::values()),
            $relations['supplier'],
            self::column(self::SUPPLIER_VAT_COLUMN, 'text', visible: false, searchable: true),
            $relations['company'],
            $relations['company_site'],
            self::column('operational_site', 'text', visible: false),
            self::column('description', 'text', sortable: true, filter: 'text', searchable: true),
            $relations['payment_method'],
            $relations['customer'],
            $relations['work_order'],
            $relations['quote'],
            $relations['opportunity'],
            $relations[self::SECTORS_COLUMN],
            self::column('is_internal_user', 'boolean', visible: false, sortable: true, filter: 'boolean'),
            self::column('taxable_total', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('vat_total', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('grand_total', 'number', sortable: true, filter: 'number'),
            self::column('lines_count', 'number', visible: false, sortable: true),
            $relations['created_by'],
            self::column('created_at', 'date', visible: false, sortable: true, filter: 'date'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return Columns::filtersFor(self::columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'purchase-orders.view'],
            ['key' => 'update', 'label' => 'actions.edit', 'icon' => 'pencil', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-orders.update'],
            ['key' => 'pdf', 'label' => 'purchaseOrders.actions.pdf', 'icon' => 'file-down', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-orders.view'],
            ['key' => 'delete', 'label' => 'purchaseOrders.actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'purchase-orders.delete'],
            ['key' => 'activity', 'label' => 'purchaseOrders.actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-orders.viewActivity'],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    private static function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array
    {
        return Columns::column($id, $type, $visible, $sortable, $filter, $options, $searchable, self::PREFIX);
    }
}
