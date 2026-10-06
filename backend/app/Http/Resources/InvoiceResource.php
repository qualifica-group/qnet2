<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceLine;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of an Invoice (spec 0194). Expects the relations loaded by
 * InvoiceService::RESOURCE_RELATIONS (lazy loading is forbidden). Amounts are
 * decimal strings; collected/residual/payment_status are derived, never stored.
 *
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $collected = $this->installments->reduce(
            fn (string $sum, InvoiceInstallment $installment): string => bcadd($sum, (string) ($installment->collected_amount ?? '0'), 2),
            '0.00',
        );

        return [
            'id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'year' => $this->year,
            'number_label' => "{$this->number}/{$this->year}",
            'document_date' => $this->document_date,
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'vat_number' => $this->customer->personalData?->vat_number,
            ],
            'payment_method' => ['id' => $this->paymentMethod->id, 'name' => $this->paymentMethod->name],
            'financial_account' => $this->financialAccount === null ? null : [
                'id' => $this->financialAccount->id,
                'name' => $this->financialAccount->name,
                'iban' => $this->financialAccount->iban,
            ],
            'proforma_request' => $this->proformaRequest === null ? null : ['id' => $this->proformaRequest->id, 'kind' => $this->proformaRequest->kind],
            'work_order' => $this->workOrder === null ? null : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            'quote' => $this->quote === null ? null : ['id' => $this->quote->id, 'code' => $this->quote->code],
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'collected_amount' => $collected,
            'residual_amount' => bcsub((string) $this->total_amount, $collected, 2),
            'payment_status' => app(InvoicePaymentStatusResolver::class)->resolve($this->installments, now()),
            'external_number' => $this->external_number,
            'external_date' => $this->external_date,
            'notes' => $this->notes,
            'internal_note' => $this->internal_note,
            'tag' => $this->tag,
            'deviation' => $this->deviation,
            'has_collections' => bccomp($collected, '0', 2) > 0,
            'lines' => $this->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'quote_line_id' => $line->quote_line_id,
                'product' => $line->product === null ? null : ['id' => $line->product->id, 'name' => $line->product->name],
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'vat_rate' => ['id' => $line->vatRate->id, 'name' => $line->vatRate->name, 'rate' => $line->vatRate->rate],
                'net_amount' => $line->net_amount,
                'vat_amount' => $line->vat_amount,
                'total_amount' => $line->total_amount,
                'sort_order' => $line->sort_order,
            ])->all(),
            'installments' => $this->installments->map(fn (InvoiceInstallment $installment): array => [
                'id' => $installment->id,
                'sequence' => $installment->sequence,
                'due_date' => $installment->due_date,
                'amount' => $installment->amount,
                'payment_method_code' => $installment->payment_method_code,
                'collected_amount' => $installment->collected_amount,
                'collected_at' => $installment->collected_at,
                'status' => $installment->status(),
            ])->all(),
            'created_by' => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
