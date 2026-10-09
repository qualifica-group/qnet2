<?php

namespace App\Tables\PurchaseRequests;

use App\Enums\PurchaseRequestLineStatus;
use App\Enums\PurchaseRequestPriority;

/**
 * Declarative column/filter/action catalogue for the `purchase-request-lines`
 * domain (spec 0208, "Gestione righe"). Pure data; the columns that come from
 * the parent request are derived (see PurchaseRequestLinesTableDefinition).
 */
final class PurchaseRequestLineColumnCatalog
{
    /** Parent columns filtered by a text "contains": id => [relation path, column]. */
    public const array TEXT_RELATIONS = [
        'purchase_request_subject' => ['purchaseRequest', 'subject'],
        'requester' => ['purchaseRequest.requester', 'name'],
        'function_manager' => ['purchaseRequest.functionManager', 'name'],
        'approved_by' => ['approvedBy', 'name'],
    ];

    /** Parent columns sortable through a subquery on purchase_requests: id => column. */
    public const array PARENT_SORTS = [
        'purchase_request_subject' => 'subject',
        'priority' => 'priority',
        'requested_at' => 'requested_at',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        $column = fn (string $id, string $type, bool $visible = true, bool $sortable = false, ?string $filter = null, ?array $options = null, bool $searchable = false): array => PurchaseRequestColumnCatalog::column($id, $type, $visible, $sortable, $filter, $options, $searchable, 'purchaseRequestLines');

        return [
            $column('purchase_request_id', 'number', visible: false, sortable: true),
            $column('purchase_request_subject', 'text', sortable: true, filter: 'text'),
            $column('priority', 'badge', sortable: true, filter: 'set', options: PurchaseRequestPriority::values()),
            $column('requester', 'text', filter: 'text'),
            $column('function_manager', 'text', filter: 'text'),
            $column('requested_at', 'date', sortable: true),
            $column('position', 'number', visible: false, sortable: true),
            $column('description', 'text', sortable: true, filter: 'text', searchable: true),
            $column('unit_of_measure', 'text'),
            $column('quantity', 'number', sortable: true, filter: 'number'),
            $column('unit_price', 'number', sortable: true, filter: 'number'),
            $column('taxable_amount', 'number', sortable: true, filter: 'number'),
            $column('vat_amount', 'number', visible: false, sortable: true, filter: 'number'),
            $column('total_amount', 'number', sortable: true, filter: 'number'),
            $column('status', 'badge', sortable: true, filter: 'set', options: PurchaseRequestLineStatus::values()),
            $column('approved_by', 'text', filter: 'text'),
            $column('approved_at', 'datetime', sortable: true, filter: 'date'),
            $column('oda_reference', 'text', sortable: true, filter: 'text'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return PurchaseRequestColumnCatalog::filtersFor(self::columns());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [
            ['key' => 'view', 'label' => 'purchaseRequestLines.actions.openRequest', 'icon' => 'eye', 'type' => 'link', 'confirm' => false, 'permission' => 'purchase-requests.view'],
            ['key' => 'history', 'label' => 'purchaseRequestLines.actions.history', 'icon' => 'history', 'type' => 'action', 'confirm' => false, 'permission' => 'purchase-requests.view'],
        ];
    }
}
