<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\QuoteLine;
use App\Models\UnitOfMeasure;
use App\Services\Commissions\QuoteCommissionPayloadRedactor;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteLine
 *
 * One row of `offer_lines`/`cost_lines` (spec 0065 data_contract, MT-05). The
 * product is read LIVE (D-7): `code`/`name`/`category`/`product_typology`
 * (spec 0099, D-5 — deliberately NOT frozen) come straight off the
 * eager-loaded `product`/`product.category` relation
 * (QuoteService::DETAIL_RELATIONS) — never duplicated on the row itself. The
 * 6 amount-bearing columns (`quantity`, `unit_price`, `net_amount`,
 * `vat_amount`, `total_amount`, plus the `vat_rate` snapshot) are the FROZEN,
 * persisted values (D-10/D-12) — never recomputed here (AC-055).
 *
 * `business_function` is the category's EFFECTIVE one
 * (CategoryHierarchy::effectiveBusinessFunction(), spec 0023): this walks
 * `parent_id` OUTSIDE the eager-loaded tree whenever the category has no own
 * `business_function_id` — one or more extra queries per DISTINCT category,
 * mirroring the SAME per-model cost ProductResource/ProductController already
 * accept for a single product. Left as-is (domain service, not this layer's
 * ownership) — flagged in the MT-05 handoff as a known N+1-adjacent cost on a
 * quote with many distinct product categories, never on a single category
 * repeated across lines within one request (PHP request-local object cache
 * inside CategoryHierarchy's own ProductCategory::find() calls is out of
 * scope here).
 */
class QuoteLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $redactor = app(QuoteCommissionPayloadRedactor::class);
        $permissions = $redactor->permissions($request->user(), $this->quote);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->summarizeProduct(),
            'quantity' => $this->quantity,
            // Spec 0088, D-5/AC-053: the FROZEN unit, or — for a historic line
            // predating the module (unit_of_measure_id NULL) — the product's
            // CURRENT unit, so an old row never renders blank.
            'unit_of_measure' => $this->summarizeUnitOfMeasure(),
            'unit_price' => $this->unit_price,
            'vat_rate_id' => $this->vat_rate_id,
            'vat_rate' => $this->summarizeVatRate(),
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'sort_order' => $this->sort_order,
            'commissions' => $this->when(
                $permissions['commissions']->visible,
                fn () => $this->commissions->map(
                    fn ($commission): QuoteLineCommissionResource => new QuoteLineCommissionResource($commission, $permissions),
                ),
            ),
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, category: array{id: int, name: string}|null, product_typology: array{id: int, name: string}|null, business_function: array{id: int, name: string}|null}|null
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
            'category' => $this->summarizeByName($product->category),
            // Spec 0099, D-5: read live through the product — the line freezes
            // no typology, so it is what SEEDS the form's client-side bucket
            // cache in edit mode.
            'product_typology' => $this->summarizeProductTypology($product->productTypology),
            'business_function' => $this->summarizeBusinessFunction($product->category),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeBusinessFunction(?ProductCategory $category): ?array
    {
        if ($category === null) {
            return null;
        }

        $effective = app(CategoryHierarchy::class)->effectiveBusinessFunction($category);

        return $effective === null ? null : ['id' => $effective['id'], 'name' => $effective['name']];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeProductTypology(?ProductTypology $productTypology): ?array
    {
        return $productTypology === null ? null : ['id' => $productTypology->id, 'name' => $productTypology->name];
    }

    /**
     * @return array{id: int, name: string, rate: string}|null
     */
    private function summarizeVatRate(): ?array
    {
        $vatRate = $this->vatRate;

        return $vatRate === null ? null : ['id' => $vatRate->id, 'name' => $vatRate->name, 'rate' => $vatRate->rate];
    }

    /**
     * Spec 0088, AC-053: the frozen `unitOfMeasure` relation when present;
     * otherwise the product's CURRENT unit (a historic line predating the
     * module). Both branches are eager-loaded by QuoteService::DETAIL_RELATIONS
     * so neither ever N+1s.
     *
     * @return array{id: int, name: string, symbol: string}|null
     */
    private function summarizeUnitOfMeasure(): ?array
    {
        $unitOfMeasure = $this->unitOfMeasure ?? $this->product?->unitOfMeasure;

        return $this->summarizeUnit($unitOfMeasure);
    }

    /**
     * @return array{id: int, name: string, symbol: string}|null
     */
    private function summarizeUnit(?UnitOfMeasure $unitOfMeasure): ?array
    {
        if ($unitOfMeasure === null) {
            return null;
        }

        return ['id' => $unitOfMeasure->id, 'name' => $unitOfMeasure->name, 'symbol' => $unitOfMeasure->symbol];
    }
}
