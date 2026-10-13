<?php

namespace App\Http\Resources;

use App\Models\PurchaseInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Projection of a PurchaseInvoiceLine (spec 0250). Expects the relations of
 * PurchaseInvoiceService::RESOURCE_RELATIONS (lazy loading is forbidden).
 *
 * @mixin PurchaseInvoiceLine
 */
class PurchaseInvoiceLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->purchaseOrderLine?->purchaseOrder;

        return [
            'id' => $this->id,
            'purchase_order_line_id' => $this->purchase_order_line_id,
            'purchase_order' => $order === null ? null : ['id' => $order->id, 'code' => $order->code()],
            'product' => $this->product === null ? null : ['id' => $this->product->id, 'name' => $this->product->name],
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'vat_rate' => $this->vatRate === null ? null : ['id' => $this->vatRate->id, 'name' => $this->vatRate->name, 'rate' => $this->vatRate->rate],
            'cost_type' => ['id' => $this->costType->id, 'name' => $this->costType->name, 'color' => $this->costType->color],
            'work_order' => $this->workOrder === null ? null : ['id' => $this->workOrder->id, 'code' => $this->workOrder->code, 'title' => $this->workOrder->title],
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'sort_order' => $this->sort_order,
        ];
    }
}
