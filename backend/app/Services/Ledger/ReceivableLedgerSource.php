<?php

declare(strict_types=1);

namespace App\Services\Ledger;

use App\DataObjects\Ledger\LedgerEntry;
use App\DataObjects\Ledger\LedgerFilters;
use App\DataObjects\Ledger\LedgerInstallmentEntry;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;

/**
 * Active side of the ledger (spec 0266): the invoices and proforma issued to a
 * registry. Sites are not on the invoice: they come from the linked quote, so
 * an invoice without a quote has none and is excluded by any site filter.
 */
final class ReceivableLedgerSource
{
    use BuildsLedgerEntries;

    /**
     * @return array<int, LedgerEntry>
     */
    public function entries(Registry $registry, LedgerFilters $filters): array
    {
        return $this->query($registry, $filters)
            ->with(['company', 'quote.companySite', 'quote.operationalSite', 'installments'])
            ->get()
            ->map(fn (Invoice $invoice): LedgerEntry => $this->entry($invoice))
            ->all();
    }

    /**
     * @return Builder<Invoice>
     */
    private function query(Registry $registry, LedgerFilters $filters): Builder
    {
        return Invoice::query()
            ->where('customer_registry_id', $registry->id)
            ->when($filters->documentType !== LedgerFilters::TYPE_ALL, fn (Builder $query) => $query->where('type', $filters->documentType))
            ->when($filters->dateTo !== null, fn (Builder $query) => $query->whereDate('document_date', '<=', $filters->dateTo))
            ->when($filters->companyIds !== [], fn (Builder $query) => $query->whereIn('company_id', $filters->companyIds))
            ->when($filters->hasSiteFilter(), fn (Builder $query) => $query->whereHas('quote', function (Builder $quote) use ($filters): void {
                $quote
                    ->when($filters->companySiteIds !== [], fn (Builder $q) => $q->whereIn('company_site_id', $filters->companySiteIds))
                    ->when($filters->operationalSiteIds !== [], fn (Builder $q) => $q->whereIn('operational_site_id', $filters->operationalSiteIds));
            }));
    }

    private function entry(Invoice $invoice): LedgerEntry
    {
        return new LedgerEntry(
            id: $invoice->id,
            documentType: $invoice->type->value,
            numberLabel: "{$invoice->number}/{$invoice->year}",
            externalNumber: $invoice->external_number,
            documentDate: $this->day($invoice->document_date),
            company: $this->companyLabel($invoice->company),
            companySite: $this->companySiteLabel($invoice->quote?->companySite),
            operationalSite: $this->operationalSiteLabel($invoice->quote?->operationalSite),
            totalCents: $this->cents($invoice->total_amount),
            installments: $invoice->installments->map(fn (InvoiceInstallment $row): LedgerInstallmentEntry => new LedgerInstallmentEntry(
                id: $row->id,
                sequence: $row->sequence,
                dueDate: $this->day($row->due_date),
                amountCents: $this->cents($row->amount),
                settledCents: $row->collected_amount === null ? null : $this->cents($row->collected_amount),
                settledAt: $this->day($row->collected_at),
            ))->all(),
        );
    }
}
