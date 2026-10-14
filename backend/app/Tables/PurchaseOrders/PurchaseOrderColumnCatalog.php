<?php

namespace App\Tables\PurchaseOrders;

use App\DataObjects\PurchaseOrders\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Tables\PurchaseRequests\PurchaseRequestColumnCatalog as Columns;

/**
 * Declarative column/filter/action catalogue for the `purchase-orders` domain
 * (spec 0210). Pure data. The status tabs send a `status` set filter (a real
 * column). `code` is derived ("{number}/{year}": sortable by year, number;
 * searchable); `supplier_vat_number` is a hidden, search-only column.
 *
 * Spec 0257, D-3: the header columns the form edits as single fields are
 * inline-editable through PurchaseOrderCellWriter. The company stays
 * read-only: it is locked once the order exists (spec 0244 D-1).
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

    /**
     * Editable relation columns: id => [for-select resource, form field, nullable, row scope, fixed picker params].
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: array<string, string>, 4: array<string, int>}>
     */
    private const array EDITABLE_RELATIONS = [
        'supplier' => ['registries', 'supplier_id', false, [], ['is_supplier' => 1]],
        'company_site' => ['company-sites', 'company_site_id', false, ['company_id' => 'company'], []],
        'operational_site' => ['operational-sites', 'operational_site_id', true, [], []],
        'payment_method' => ['payment-methods', 'payment_method_id', true, [], []],
        'customer' => ['registries', 'customer_id', true, [], []],
        'work_order' => ['work-orders', 'work_order_id', true, [], []],
        'quote' => ['quotes', 'quote_id', true, [], []],
        'opportunity' => ['opportunities', 'opportunity_id', true, [], []],
    ];

    /** Editable scalar columns, written under their own id. */
    private const array EDITABLE_SCALARS = ['order_date', 'description', 'is_internal_user'];

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

        $columns = [
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

        return array_map(self::withEditing(...), $columns);
    }

    /**
     * The editable columns whose field stays writable past approval (spec 0259, D-5).
     *
     * @return array<int, string>
     */
    public static function serviceColumnIds(): array
    {
        $ids = [];

        foreach (self::columns() as $column) {
            $field = $column['editableField'] ?? $column['id'];

            if (($column['editable'] ?? false) === true && in_array($field, PurchaseOrderData::SERVICE_FIELDS, true)) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * The inline-editing keys of an editable column (spec 0257, D-3); any other column is returned as is.
     *
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private static function withEditing(array $column): array
    {
        $id = $column['id'];

        if (in_array($id, self::EDITABLE_SCALARS, true)) {
            return [...$column, 'editable' => true, 'nullable' => $id === 'is_internal_user'];
        }

        if ($id === self::SECTORS_COLUMN) {
            return [...$column, 'editable' => true, 'editor' => 'multiselect', 'relation' => ['resource' => 'sectors'], 'editableField' => 'sector_ids'];
        }

        if (! isset(self::EDITABLE_RELATIONS[$id])) {
            return $column;
        }

        [$resource, $field, $nullable, $scope, $params] = self::EDITABLE_RELATIONS[$id];
        $relation = ['resource' => $resource];

        if ($scope !== []) {
            $relation += ['scope' => $scope, 'lockScope' => true];
        }

        if ($params !== []) {
            $relation['params'] = $params;
        }

        return [...$column, 'editable' => true, 'relation' => $relation, 'editableField' => $field, 'nullable' => $nullable];
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
