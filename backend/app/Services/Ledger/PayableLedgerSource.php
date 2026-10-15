<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\DataObjects\Ledger\LedgerEntry;
use App\DataObjects\Ledger\LedgerFilters;
use App\DataObjects\Ledger\LedgerInstallmentEntry;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Payable side of the ledger (spec 0266): the proforma and invoices a supplier
 * registry billed to the company. The sites are columns of the document.
 */
final class PayableLedgerSource
{
    use BuildsLedgerEntries;

    public function hasDocuments(Registry $registry): bool
    {
        return PurchaseInvoice::query()->where('supplier_id', $registry->id)->exists();
    }

    /**
     * @return array<int, LedgerEntry>
     */
    public function entries(Registry $registry, LedgerFilters $filters): array
    {
        return $this->query($registry, $filters)
            ->with(['company', 'companySite', 'operationalSite', 'installments'])
            ->get()
            ->map(fn (PurchaseInvoice $document): LedgerEntry => $this->entry($document))
            ->all();
    }

    /**
     * @return Builder<PurchaseInvoice>
     */
    private function query(Registry $registry, LedgerFilters $filters): Builder
    {
        return PurchaseInvoice::query()
            ->where('supplier_id', $registry->id)
            ->when($filters->documentType !== LedgerFilters::TYPE_ALL, fn (Builder $query) => $query->where('document_type', $filters->documentType))
            ->when($filters->dateTo !== null, fn (Builder $query) => $query->whereDate('document_date', '<=', $filters->dateTo))
            ->when($filters->companyIds !== [], fn (Builder $query) => $query->whereIn('company_id', $filters->companyIds))
            ->when($filters->companySiteIds !== [], fn (Builder $query) => $query->whereIn('company_site_id', $filters->companySiteIds))
            ->when($filters->operationalSiteIds !== [], fn (Builder $query) => $query->whereIn('operational_site_id', $filters->operationalSiteIds));
    }

    private function entry(PurchaseInvoice $document): LedgerEntry
    {
        return new LedgerEntry(
            id: $document->id,
            documentType: $document->document_type->value,
            numberLabel: $document->document_number,
            externalNumber: null,
            documentDate: $this->day($document->document_date),
            company: $this->companyLabel($document->company),
            companySite: $this->companySiteLabel($document->companySite),
            operationalSite: $this->operationalSiteLabel($document->operationalSite),
            totalCents: $this->cents($document->payableAmount()),
            installments: $document->installments->map(fn (PurchaseInvoiceInstallment $row): LedgerInstallmentEntry => new LedgerInstallmentEntry(
                id: $row->id,
                sequence: $row->sequence,
                dueDate: $this->day($row->due_date),
                amountCents: $this->cents($row->amount),
                settledCents: $row->paid_amount === null ? null : $this->cents($row->paid_amount),
                settledAt: $this->day($row->paid_at),
            ))->all(),
        );
    }
}
