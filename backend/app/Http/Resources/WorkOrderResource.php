<?php

namespace App\Http\Resources;

use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderStatusResolver;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkOrderDetail shape (spec 0093 data_contract). `status` is ALWAYS
 * computed via WorkOrderStatusResolver (D-3), never read off a column.
 * `contract_number` is `quote.code`, derived and read-only (D-2) — never a
 * column of its own. `quote_lines` composes its label live from
 * `product.code`/`product.name` (D-8, spec 0065 D-7's own precedent).
 *
 * @mixin WorkOrder
 */
class WorkOrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = app(WorkOrderStatusResolver::class)->resolve($this->resource);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'type' => $this->type?->value,
            'status' => [
                'value' => $status->value,
                'is_force_closed' => $this->is_force_closed,
            ],
            'is_force_closed' => $this->is_force_closed,
            'force_close_reason' => $this->force_close_reason,
            'callback_date' => $this->callback_date,
            'description' => $this->description,
            'internal_notes' => $this->internal_notes,
            'contract_number' => $this->quote?->code,
            'quote' => $this->summarizeQuote(),
            'quote_lines' => $this->summarizeQuoteLines(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, code: string, title: string}|null
     */
    private function summarizeQuote(): ?array
    {
        $quote = $this->quote;

        if ($quote === null) {
            return null;
        }

        return ['id' => $quote->id, 'code' => $quote->code, 'title' => $quote->title];
    }

    /**
     * @return array<int, array{id: int, sort_order: int, product: array{id: int, code: string, name: string}|null}>
     */
    private function summarizeQuoteLines(): array
    {
        /** @var Collection<int, QuoteLine> $lines */
        $lines = $this->quoteLines;

        return $lines->map(static function ($line): array {
            $product = $line->product;

            return [
                'id' => $line->id,
                'sort_order' => $line->sort_order,
                'product' => $product === null ? null : ['id' => $product->id, 'code' => $product->code, 'name' => $product->name],
            ];
        })->all();
    }
}
