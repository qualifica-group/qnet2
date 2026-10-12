<?php

namespace App\Http\Resources;

use App\Models\PurchaseOrderMergeLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One entry of the merge history of an order (spec 0210, D-19), read from the
 * snapshot taken before the lines were moved. Expects `performedBy:id,name`.
 *
 * @mixin PurchaseOrderMergeLog
 */
class PurchaseOrderMergeLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $header = $this->source_snapshot['header'];
        $moved = $this->moved_line_ids;

        return [
            'id' => $this->id,
            'source_purchase_order_id' => $this->source_purchase_order_id,
            'source_code' => $this->source_code,
            'source_action' => $this->source_action,
            'source_description' => $header['description'],
            'source_order_date' => $header['order_date'],
            'source_grand_total' => $header['grand_total'],
            'moved_lines' => collect($this->source_snapshot['lines'])
                ->filter(fn (array $line): bool => in_array($line['id'], $moved, true))
                ->map(fn (array $line): array => [
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'taxable_amount' => $line['taxable_amount'],
                    'total_amount' => $line['total_amount'],
                ])->values()->all(),
            'performed_by' => ['id' => $this->performedBy->id, 'name' => $this->performedBy->name],
            'created_at' => $this->created_at,
        ];
    }
}
