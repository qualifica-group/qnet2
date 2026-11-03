<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Services\Shared\AbstractDocumentForSelectService;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /api/purchase-orders/for-select (ADR 0011, spec 0347). Whoever holds
 * `purchase-orders.view` sees every order in the index (D-20): no row scope here
 * either.
 */
final class PurchaseOrderForSelectService extends AbstractDocumentForSelectService
{
    protected function baseQuery(): Builder
    {
        return PurchaseOrder::query()
            ->select(['purchase_orders.id', 'purchase_orders.number', 'purchase_orders.year', 'purchase_orders.order_date', 'purchase_orders.supplier_id'])
            ->with('supplier:id,name');
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $this->searchNumberYear($query, $term, 'purchase_orders');
        $query->orWhere('purchase_orders.description', 'like', '%'.$term.'%')
            ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$term.'%'));
    }

    protected function applyOrder(Builder $query): Builder
    {
        return $query->orderByDesc('purchase_orders.order_date')->orderByDesc('purchase_orders.id');
    }

    protected function keyColumn(): string
    {
        return 'purchase_orders.id';
    }
}
