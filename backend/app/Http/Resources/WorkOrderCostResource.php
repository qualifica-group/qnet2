<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WorkOrderCost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderCost
 *
 * One actual cost of a commessa (spec 0190, WorkOrderCostLine). The product,
 * VAT rate and unit-of-measure shapes are the ones of QuoteLineResource; the
 * frozen unit falls back to the product's current one when absent, exactly
 * like a historic quote line.
 */
class WorkOrderCostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;
        $unit = $this->unitOfMeasure ?? $product?->unitOfMeasure;

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $product === null ? null : [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'category' => $product->category === null ? null : ['id' => $product->category->id, 'name' => $product->category->name],
            ],
            'quantity' => $this->quantity,
            'unit_of_measure' => $unit === null ? null : ['id' => $unit->id, 'name' => $unit->name, 'symbol' => $unit->symbol],
            'unit_price' => $this->unit_price,
            'vat_rate_id' => $this->vat_rate_id,
            'vat_rate' => $this->vatRate === null ? null : ['id' => $this->vatRate->id, 'name' => $this->vatRate->name, 'rate' => $this->vatRate->rate],
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'quote_line_id' => $this->quote_line_id,
            'incurred_on' => $this->incurred_on->format('Y-m-d'),
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier === null ? null : ['id' => $this->supplier->id, 'name' => $this->supplier->name],
            'document_reference' => $this->document_reference,
            'additional_description' => $this->additional_description,
            'sort_order' => $this->sort_order,
        ];
    }
}
