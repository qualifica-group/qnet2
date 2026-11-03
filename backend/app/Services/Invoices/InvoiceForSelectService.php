<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Services\Shared\AbstractDocumentForSelectService;
use Illuminate\Database\Eloquent\Builder;

/**
 * GET /api/invoices/for-select (ADR 0011, spec 0347). The `invoices` index has
 * no row scope (InvoicesTableDefinition::baseQuery), so neither has this.
 */
final class InvoiceForSelectService extends AbstractDocumentForSelectService
{
    protected function baseQuery(): Builder
    {
        return Invoice::query()
            ->select(['invoices.id', 'invoices.number', 'invoices.year', 'invoices.document_date', 'invoices.customer_registry_id'])
            ->with('customer:id,name');
    }

    protected function applySearch(Builder $query, string $term): void
    {
        $this->searchNumberYear($query, $term, 'invoices');
        $query->orWhere('invoices.external_number', 'like', '%'.$term.'%')
            ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$term.'%'));
    }

    protected function applyOrder(Builder $query): Builder
    {
        return $query->orderByDesc('invoices.document_date')->orderByDesc('invoices.id');
    }

    protected function keyColumn(): string
    {
        return 'invoices.id';
    }
}
