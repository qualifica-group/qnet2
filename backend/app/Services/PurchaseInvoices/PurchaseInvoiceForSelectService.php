<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseInvoice;
use App\Services\Shared\AbstractDocumentForSelectService;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /api/purchase-invoices/for-select (ADR 0011, spec 0347). The
 * `purchase-invoices` index has no row scope (PurchaseInvoicesTableDefinition::
 * baseQuery), so neither has this.
 */
final class PurchaseInvoiceForSelectService extends AbstractDocumentForSelectService
{
    protected function baseQuery(): Builder
    {
        return PurchaseInvoice::query()
            ->select(['purchase_invoices.id', 'purchase_invoices.document_number', 'purchase_invoices.document_date', 'purchase_invoices.supplier_id'])
            ->with('supplier:id,name');
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $query->where('purchase_invoices.document_number', 'like', '%'.$term.'%')
            ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$term.'%'));
    }

    protected function applyOrder(Builder $query): Builder
    {
        return $query->orderByDesc('purchase_invoices.document_date')->orderByDesc('purchase_invoices.id');
    }

    protected function keyColumn(): string
    {
        return 'purchase_invoices.id';
    }
}
