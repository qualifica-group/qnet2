<?php

namespace App\Http\Resources;

use App\Models\InstallmentCompensation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of an InstallmentCompensation (spec 0364). Expects
 * InstallmentCompensation::RELATIONS loaded (lazy loading is forbidden).
 *
 * @mixin InstallmentCompensation
 */
class InstallmentCompensationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $receivable = $this->invoiceInstallment;
        $payable = $this->purchaseInvoiceInstallment;
        $actor = $request->user();

        return [
            'id' => $this->id,
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'compensated_at' => $this->compensated_at->toDateString(),
            'amount' => (string) $this->amount,
            'note' => $this->note,
            'invoice_installment' => [
                'id' => $receivable->id,
                'sequence' => $receivable->sequence,
                'invoice' => ['id' => $receivable->invoice->id, 'number_label' => "{$receivable->invoice->number}/{$receivable->invoice->year}"],
            ],
            'purchase_invoice_installment' => [
                'id' => $payable->id,
                'sequence' => $payable->sequence,
                'purchase_invoice' => ['id' => $payable->purchaseInvoice->id, 'number_label' => (string) $payable->purchaseInvoice->document_number],
            ],
            'created_by' => $this->creator === null ? null : ['id' => $this->creator->id, 'name' => $this->creator->name],
            'created_at' => $this->created_at,
            'can_delete' => $actor !== null && $actor->can('invoices.collect') && $actor->can('purchase-invoices.pay'),
        ];
    }
}
