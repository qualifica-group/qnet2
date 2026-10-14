<?php

namespace App\Tables\PurchaseRequests;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestStatus;

/**
 * Declarative column/filter/action catalogue for the `purchase-requests` domain
 * (spec 0208). Pure data. `line_status` is a hidden filter-only column: the
 * status tabs send it, and it needs a catalogue entry to reach the server.
 * `supplier_vat_number` is a hidden, search-only column (it searches the
 * suppliers of the lines).
 *
 * Spec 0256, D-3: the header columns the form edits as single fields are
 * inline-editable through PurchaseRequestCellWriter (the form's own
 * PurchaseRequestWriteRequest + PurchaseRequestService). The company stays
 * read-only: it changes together with its site, from the detail.
 */
final class PurchaseRequestColumnCatalog
{
    /** Relation columns filtered by a text "contains" on [relation, column]. */
    public const array TEXT_RELATIONS = [
        'requester' => ['requester', 'name'],
        'function_manager' => ['functionManager', 'name'],
        'customer' => ['customer', 'name'],
        'suppliers' => ['lines.supplier', 'name'],
        'work_order' => ['workOrder', 'code'],
        'company' => ['company', 'denomination'],
        'business_function' => ['businessFunction', 'name'],
        'created_by' => ['createdBy', 'name'],
    ];

    /** Sortable relation columns: id => [related table, foreign key on purchase_requests, column]. */
    public const array SORT_RELATIONS = [
        'requester' => ['users', 'purchase_requests.requester_id', 'name'],
        'function_manager' => ['users', 'purchase_requests.function_manager_id', 'name'],
        'customer' => ['registries', 'purchase_requests.customer_id', 'name'],
        'priority' => ['purchase_request_priorities', 'purchase_requests.purchase_request_priority_id', 'sort_order'],
    ];

    /**
     * Editable relation columns: id => [for-select resource, form field, nullable, picker scope].
     *
     * @var array<string, array{0: string, 1: string, 2: bool, 3: array<string, string>}>
     */
    private const array EDITABLE_RELATIONS = [
        'priority' => ['purchase-request-priorities', 'purchase_request_priority_id', false, []],
        'requester' => ['users', 'requester_id', false, []],
        'function_manager' => ['users', 'function_manager_id', false, []],
        'customer' => ['registries', 'customer_id', true, []],
        'work_order' => ['work-orders', 'work_order_id', true, []],
        'company_site' => ['company-sites', 'company_site_id', false, ['company_id' => 'company']],
        'operational_site' => ['operational-sites', 'operational_site_id', false, []],
        'business_function' => ['business-functions', 'business_function_id', false, []],
    ];

    /** Editable scalar columns, written under their own id. */
    private const array EDITABLE_SCALARS = ['subject', 'requested_at'];

    /** Lookup column: filtered by priority name, sorted by the priority's sort_order. */
    public const string PRIORITY_COLUMN = 'priority';

    public const string LINE_STATUS_COLUMN = 'line_status';

    /** Distinct suppliers of the request's lines (spec 0209, D-6). */
    public const string SUPPLIERS_COLUMN = 'suppliers';

    public const string SUPPLIER_VAT_COLUMN = 'supplier_vat_number';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        $columns = [
            self::column('subject', 'text', sortable: true, filter: 'text', searchable: true),
            self::column('requested_at', 'date', sortable: true, filter: 'date'),
            self::column(self::PRIORITY_COLUMN, 'badge', sortable: true, filter: 'set'),
            self::column('status', 'badge', sortable: true, filter: 'set', options: PurchaseRequestStatus::values()),
            self::column('line_status_counts', 'text'),
        ];

        foreach (array_keys(self::TEXT_RELATIONS) as $id) {
            $columns[] = self::column($id, 'text', visible: in_array($id, ['requester', 'function_manager', self::SUPPLIERS_COLUMN], true), sortable: isset(self::SORT_RELATIONS[$id]), filter: 'text');
        }

        $columns = [
            ...$columns,
            self::column('company_site', 'text', visible: false),
            self::column('operational_site', 'text', visible: false),
            self::column('taxable_total', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('vat_total', 'number', visible: false, sortable: true, filter: 'number'),
            self::column('grand_total', 'number', sortable: true, filter: 'number'),
            self::column(self::LINE_STATUS_COLUMN, 'badge', visible: false, filter: 'set', options: PurchaseRequestLineStatus::values()),
            self::column(self::SUPPLIER_VAT_COLUMN, 'text', visible: false, searchable: true),
        ];

        return array_map(self::withEditing(...), $columns);
    }

    /**
     * The inline-editing keys of an editable column (spec 0256, D-3); any other column is returned as is.
     *
     * @param  array<string, mixed>  $column
     * @return array<string, mixed>
     */
    private static function withEditing(array $column): array
    {
        $id = $column['id'];

        if (in_array($id, self::EDITABLE_SCALARS, true)) {
            return [...$column, 'editable' => true, 'nullable' => false];
        }

        if (! isset(self::EDITABLE_RELATIONS[$id])) {
            return $column;
        }

        [$resource, $field, $nullable, $scope] = self::EDITABLE_RELATIONS[$id];
        $relation = $scope === [] ? ['resource' => $resource] : ['resource' => $resource, 'scope' => $scope, 'lockScope' => true];

        return [...$column, 'editable' => true, 'relation' => $relation, 'editableField' => $field, 'nullable' => $nullable];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return self::filtersFor(self::columns());
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, mixed>>
     */
    public static function filtersFor(array $columns): array
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
            ['key' => 'view', 'label' => 'actions.view', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'purchase-requests.view'],
            ['key' => 'notify_manager', 'label' => 'purchaseRequests.actions.notifyManager', 'icon' => 'send', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-requests.update'],
            ['key' => 'close', 'label' => 'purchaseRequests.actions.close', 'icon' => 'lock', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-requests.close'],
            ['key' => 'delete', 'label' => 'actions.delete', 'icon' => 'trash', 'type' => 'danger', 'confirm' => true, 'permission' => 'purchase-requests.delete'],
            ['key' => 'activity', 'label' => 'actions.activity', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-requests.viewActivity'],
        ];
    }

    /**
     * @param  array<int, string>|null  $options
     * @return array<string, mixed>
     */
    public static function column(string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false, string $prefix = 'purchaseRequests'): array
    {
        $column = [
            'id' => $id,
            'label' => "{$prefix}.columns.{$id}",
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

        if ($searchable) {
            $column['searchable'] = true;
        }

        return $column;
    }
}
