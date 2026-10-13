<?php

namespace App\Http\Resources;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\PurchaseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseInvoice (spec 0250). Expects the relations of
 * PurchaseInvoiceService::RESOURCE_RELATIONS (lazy loading is forbidden).
 * Amounts are decimal strings; paid/residual are derived from the schedule.
 *
 * @mixin PurchaseInvoice
 */
class PurchaseInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $paid = $this->installments->reduce(
            fn (string $sum, PurchaseInvoiceInstallment $row): string => bcadd($sum, (string) ($row->paid_amount ?? '0'), 2),
            '0.00',
        );

        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'document_date' => $this->document_date->toDateString(),
            'company' => ['id' => $this->company->id, 'name' => $this->company->denomination],
            'supplier' => ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'payment_method' => ['id' => $this->paymentMethod->id, 'name' => $this->paymentMethod->name],
            'financial_account' => $this->financialAccount === null ? null : [
                'id' => $this->financialAccount->id,
                'name' => $this->financialAccount->name,
                'iban' => $this->financialAccount->iban,
            ],
            'company_site' => $this->companySite === null ? null : ['id' => $this->companySite->id, 'name' => $this->companySite->name],
            'operational_site' => $this->operationalSite === null ? null : ['id' => $this->operationalSite->id, 'name' => $this->operationalSite->name],
            'cost_type' => $this->costType === null ? null : ['id' => $this->costType->id, 'name' => $this->costType->name, 'color' => $this->costType->color],
            'work_order' => $this->workOrder === null ? null : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            'notes' => $this->notes,
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $paid,
            'residual_amount' => bcsub((string) $this->total_amount, $paid, 2),
            'has_payments' => bccomp($paid, '0', 2) > 0,
            'purchase_orders' => $this->purchaseOrders
                ->sortBy([['year', 'asc'], ['number', 'asc']])
                ->map(fn (PurchaseOrder $order): array => ['id' => $order->id, 'code' => $order->code()])
                ->values()
                ->all(),
            'lines' => PurchaseInvoiceLineResource::collection($this->lines)->resolve($request),
            'installments' => $this->installments->map(fn (PurchaseInvoiceInstallment $row): array => $this->installment($row))->all(),
            'created_by' => ['id' => $this->createdBy->id, 'name' => $this->createdBy->name],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function installment(PurchaseInvoiceInstallment $row): array
    {
        $daysOverdue = $row->daysOverdue();

        return [
            'id' => $row->id,
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'amount' => $row->amount,
            'payment_method_code' => $row->payment_method_code,
            'paid_amount' => $row->paid_amount,
            'paid_at' => $row->paid_at?->toDateString(),
            'status' => $row->status(),
            'residual_amount' => $row->residualAmount(),
            'is_overdue' => $daysOverdue > 0,
            'days_overdue' => $daysOverdue,
            'financial_account' => $row->financialAccount === null ? null : ['id' => $row->financialAccount->id, 'name' => $row->financialAccount->name],
        ];
    }
}
