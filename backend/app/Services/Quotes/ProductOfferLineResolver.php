<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Product;

/**
 * Builds the REVENUE `offer_lines` a Lead -> Opportunity conversion (spec
 * 0094, D-3/D-8) hands to QuoteService::create(): one row per distinct
 * product of interest, `quantity` 1, `unit_price`/`vat_rate_id` read
 * straight off the Product — the server-side counterpart of the quotes
 * form's own `lineValuesFromProduct` precompilation (frontend), which this
 * conversion has none of, since it never renders a form.
 *
 * `unit_of_measure_id` is deliberately absent from the built row: it is
 * frozen by QuoteLineWriter from the Product at write time (spec 0088, D-5),
 * never a client input — the conversion does not aggire that freeze.
 * `sortOrder` is left null: QuoteLineData already treats that as "use this
 * row's own index in the submitted array" (AC-038), which IS the progressive
 * order AC-062 asks for.
 */
final class ProductOfferLineResolver
{
    /**
     * @param  array<int, int>  $productIds  distinct product ids (the caller
     *                                       already deduped the set, AC-066)
     * @return array<int, QuoteLineData>
     */
    public function resolve(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        // One query for the whole set, never one per product.
        $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

        return array_values(array_map(
            fn (int $productId): QuoteLineData => $this->lineFor($products[$productId]),
            $productIds,
        ));
    }

    private function lineFor(Product $product): QuoteLineData
    {
        return new QuoteLineData(
            productId: $product->id,
            quantity: 1.0,
            unitPrice: (float) ($product->price ?? 0),
            vatRateId: $product->vat_rate_id,
            sortOrder: null,
        );
    }
}
