<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseInvoiceLine;
use App\Models\WorkOrder;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Builds the read-only invoices payload of spec 0282: the commessa's active
 * documents (`invoices.work_order_id`, D-5) and the passive ones having at
 * least one LINE on it (D-2; the header work_order_id is only a default).
 * Collected/paid/residual mirror InvoiceResource / PurchaseInvoiceResource.
 * Amounts are decimal strings (bcmath).
 */
final class WorkOrderInvoiceOverviewBuilder
{
    private const string ZERO = '0.00';

    public function __construct(private readonly InvoicePaymentStatusResolver $paymentStatus) {}

    /**
     * @return array{active: array<int, array<string, mixed>>, passive: array<int, array<string, mixed>>, totals: array<string, array<string, string>>}
     */
    public function build(WorkOrder $workOrder): array
    {
        // Step 1: load both document sets with their relations (no per-document queries)
        $invoices = Invoice::query()
            ->where('work_order_id', $workOrder->id)
            ->with(['customer', 'company', 'installments'])
            ->orderByDesc('document_date')->orderByDesc('id')
            ->get();
        $purchaseInvoices = PurchaseInvoice::query()
            ->whereHas('lines', fn ($query) => $query->where('work_order_id', $workOrder->id))
            ->with([
                'supplier', 'company', 'installments',
                'lines' => fn (HasMany $query) => $query->where('work_order_id', $workOrder->id),
            ])
            ->orderByDesc('document_date')->orderByDesc('id')
            ->get();

        // Step 2: project each document to the fixed shape
        $active = $invoices->map(fn (Invoice $invoice): array => $this->activeRow($invoice))->all();
        $passive = $purchaseInvoices->map(fn (PurchaseInvoice $document): array => $this->passiveRow($document))->all();

        // Step 3: totals are the sums of the listed rows
        return [
            'active' => $active,
            'passive' => $passive,
            'totals' => [
                'active' => $this->sums($active, ['total_amount', 'collected_amount', 'residual_amount']),
                'passive' => $this->sums($passive, ['total_amount', 'work_order_amount', 'paid_amount', 'residual_amount']),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeRow(Invoice $invoice): array
    {
        $collected = $invoice->installments->reduce(
            fn (string $sum, InvoiceInstallment $row): string => bcadd($sum, (string) ($row->collected_amount ?? '0'), 2),
            self::ZERO,
        );

        return [
            'id' => $invoice->id,
            'type' => $invoice->type->value,
            'number_label' => "{$invoice->number}/{$invoice->year}",
            'document_date' => $invoice->document_date->toDateString(),
            'external_number' => $invoice->external_number,
            'external_date' => $invoice->external_date?->toDateString(),
            'customer' => ['id' => $invoice->customer->id, 'name' => $invoice->customer->name],
            'company' => ['id' => $invoice->company->id, 'name' => $invoice->company->denomination],
            'net_amount' => (string) $invoice->net_amount,
            'vat_amount' => (string) $invoice->vat_amount,
            'total_amount' => (string) $invoice->total_amount,
            'collected_amount' => $collected,
            'residual_amount' => bcsub((string) $invoice->total_amount, $collected, 2),
            'payment_status' => $this->paymentStatus->resolve($invoice->installments, now())->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function passiveRow(PurchaseInvoice $document): array
    {
        $paid = $document->installments->reduce(
            fn (string $sum, PurchaseInvoiceInstallment $row): string => bcadd($sum, (string) ($row->paid_amount ?? '0'), 2),
            self::ZERO,
        );
        // The eager load is already narrowed to the commessa's lines.
        $workOrderAmount = $document->lines->reduce(
            fn (string $sum, PurchaseInvoiceLine $line): string => bcadd($sum, (string) $line->total_amount, 2),
            self::ZERO,
        );

        return [
            'id' => $document->id,
            'document_type' => $document->document_type->value,
            'document_number' => $document->document_number,
            'document_date' => $document->document_date->toDateString(),
            'supplier' => ['id' => $document->supplier->id, 'name' => $document->supplier->name],
            'company' => ['id' => $document->company->id, 'name' => $document->company->denomination],
            'total_amount' => (string) $document->total_amount,
            'work_order_amount' => $workOrderAmount,
            'paid_amount' => $paid,
            'residual_amount' => bcsub($document->payableAmount(), $paid, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $columns
     * @return array<string, string>
     */
    private function sums(array $rows, array $columns): array
    {
        $totals = [];

        foreach ($columns as $column) {
            $totals[$column] = array_reduce($rows, fn (string $sum, array $row): string => bcadd($sum, $row[$column], 2), self::ZERO);
        }

        return $totals;
    }
}
