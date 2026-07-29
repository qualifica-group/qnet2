<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * For-select projection of a Product (GET /api/products/for-select).
 *
 * Minimal by design (ADR 0011): label = name, `subtitle` = the product's own
 * category (eager-loaded by ProductService::forSelect, never a query per
 * row) — the "prodotti di interesse" picker shows it so a cross-category
 * pick is recognizable BEFORE it adds a product line to the opportunity.
 *
 * `meta` (spec 0065, AC-009) is ADDITIVE: `code`/`price`/`cost`/
 * `vat_rate_id`/`vat_rate_name`/`vat_rate` let the Quote line form
 * precompile `unit_price` and the VAT rate from this SAME pick — no
 * existing key changes name or type.
 *
 * @mixin Product
 */
class ProductForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->name,
            'subtitle' => $this->category?->name,
            'meta' => [
                'code' => $this->code,
                'price' => $this->price,
                'cost' => $this->cost,
                'vat_rate_id' => $this->vat_rate_id,
                'vat_rate_name' => $this->vatRate?->name,
                'vat_rate' => $this->vatRate?->rate,
            ],
        ];
    }
}
