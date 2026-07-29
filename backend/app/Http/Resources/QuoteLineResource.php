<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductCategory;
use App\Models\QuoteLine;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuoteLine
 *
 * One row of `offer_lines`/`cost_lines` (spec 0065 data_contract, MT-05). The
 * product is read LIVE (D-7): `code`/`name`/`category` come straight off the
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
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->summarizeProduct(),
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'vat_rate_id' => $this->vat_rate_id,
            'vat_rate' => $this->summarizeVatRate(),
            'net_amount' => $this->net_amount,
            'vat_amount' => $this->vat_amount,
            'total_amount' => $this->total_amount,
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, category: array{id: int, name: string}|null, business_function: array{id: int, name: string}|null}|null
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
     * @return array{id: int, name: string, rate: string}|null
     */
    private function summarizeVatRate(): ?array
    {
        $vatRate = $this->vatRate;

        return $vatRate === null ? null : ['id' => $vatRate->id, 'name' => $vatRate->name, 'rate' => $vatRate->rate];
    }
}
