<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order that can be merged into the current one, with its lines (spec 0210,
 * D-19). Expects `lines` loaded.
 *
 * @mixin PurchaseOrder
 */
class PurchaseOrderMergeCandidateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code(),
            'order_date' => $this->order_date->toDateString(),
            'description' => $this->description,
            'status' => $this->status,
            'grand_total' => $this->grand_total,
            'lines' => $this->lines->map(fn (PurchaseOrderLine $line): array => [
                'id' => $line->id,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'taxable_amount' => $line->taxable_amount,
                'total_amount' => $line->total_amount,
            ])->all(),
        ];
    }
}
