<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Models\UnitOfMeasure;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of GET /api/contracts/{contract}/programmable-lines (spec 0095,
 * D-6). A deliberately SEPARATE projection from QuoteLineResource (D-6's own
 * reasoning): that one is shared with the Offerta/Gestione Richieste tabs,
 * and adding the "occupied by which work order" fact there would force an
 * eager load onto contexts that never need it. `unit_of_measure` mirrors
 * QuoteLineResource's own fallback (spec 0088, D-5): the frozen unit when
 * present, otherwise the product's CURRENT one.
 *
 * @mixin QuoteLine
 */
class ProgrammableQuoteLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sort_order' => $this->sort_order,
            'product' => $this->summarizeProduct(),
            'quantity' => $this->quantity,
            'unit_of_measure' => $this->summarizeUnitOfMeasure(),
            'work_order' => $this->summarizeWorkOrder(),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, category: array{id: int, name: string}|null}|null
     */
    private function summarizeProduct(): ?array
    {
        $product = $this->product;

        if ($product === null) {
            return null;
        }

        return [
            'id' => $product->id,
            'code' => $product->code,
            'name' => $product->name,
            'category' => $this->summarizeCategory($product->category),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeCategory(?ProductCategory $category): ?array
    {
        return $category === null ? null : ['id' => $category->id, 'name' => $category->name];
    }

    /**
     * @return array{id: int, name: string, symbol: string}|null
     */
    private function summarizeUnitOfMeasure(): ?array
    {
        $unitOfMeasure = $this->unitOfMeasure ?? $this->product?->unitOfMeasure;

        if ($unitOfMeasure instanceof UnitOfMeasure) {
            return ['id' => $unitOfMeasure->id, 'name' => $unitOfMeasure->name, 'symbol' => $unitOfMeasure->symbol];
        }

        return null;
    }

    /**
     * AC-021: the ONE work order already covering this line (D-4's unique
     * pivot on `quote_line_id` guarantees at most one), or null when free.
     *
     * @return array{id: int, code: string}|null
     */
    private function summarizeWorkOrder(): ?array
    {
        /** @var WorkOrder|null $workOrder */
        $workOrder = $this->workOrders->first();

        return $workOrder === null ? null : ['id' => $workOrder->id, 'code' => $workOrder->code];
    }
}
