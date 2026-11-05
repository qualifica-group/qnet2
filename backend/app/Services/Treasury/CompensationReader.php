<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Enums\InvoiceType;
use App\Enums\PurchaseInvoiceType;
use App\Models\InstallmentCompensation;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads of the compensation feature (spec 0364): the open installments a
 * registry can compensate in one company, and the compensations of a registry.
 */
final class CompensationReader
{
    /**
     * @return array{receivables: array<int, array<string, mixed>>, payables: array<int, array<string, mixed>>}
     */
    public function candidates(Registry $registry, int $companyId): array
    {
        $receivables = $this->open(InvoiceInstallment::query(), 'collected_amount')
            ->whereHas('invoice', fn (Builder $query) => $query
                ->where('type', InvoiceType::Invoice->value)
                ->where('customer_registry_id', $registry->id)
                ->where('company_id', $companyId))
            ->with('invoice:id,number,year,document_date')
            ->get();

        $payables = $this->open(PurchaseInvoiceInstallment::query(), 'paid_amount')
            ->whereHas('purchaseInvoice', fn (Builder $query) => $query
                ->where('document_type', PurchaseInvoiceType::Invoice->value)
                ->where('supplier_id', $registry->id)
                ->where('company_id', $companyId))
            ->with('purchaseInvoice:id,document_number,document_date')
            ->get();

        return [
            'receivables' => $receivables->map(fn (InvoiceInstallment $row): array => [
                ...$this->installmentRow($row),
                'invoice' => ['id' => $row->invoice->id, 'number_label' => "{$row->invoice->number}/{$row->invoice->year}", 'date' => $row->invoice->document_date->toDateString()],
            ])->all(),
            'payables' => $payables->map(fn (PurchaseInvoiceInstallment $row): array => [
                ...$this->installmentRow($row),
                'purchase_invoice' => ['id' => $row->purchaseInvoice->id, 'number_label' => (string) $row->purchaseInvoice->document_number, 'date' => $row->purchaseInvoice->document_date->toDateString()],
            ])->all(),
        ];
    }

    /**
     * @return Collection<int, InstallmentCompensation>
     */
    public function forRegistry(Registry $registry, ?int $companyId): Collection
    {
        return InstallmentCompensation::query()
            ->where('registry_id', $registry->id)
            ->when($companyId !== null, fn (Builder $query) => $query->where('company_id', $companyId))
            ->with(InstallmentCompensation::RELATIONS)
            ->orderByDesc('compensated_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Open: nothing collected/paid, no write-off, not compensated, something to settle.
     *
     * @template TModel of InvoiceInstallment|PurchaseInvoiceInstallment
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function open(Builder $query, string $settledColumn): Builder
    {
        return $query
            ->whereNull($settledColumn)
            ->whereNull('write_off_amount')
            ->whereNull('compensation_id')
            ->where('amount', '>', 0)
            ->orderBy('due_date')
            ->orderBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function installmentRow(InvoiceInstallment|PurchaseInvoiceInstallment $row): array
    {
        return [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'residual' => $row->residualAmount(),
        ];
    }
}
