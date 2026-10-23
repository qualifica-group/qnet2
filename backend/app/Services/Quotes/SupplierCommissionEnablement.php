<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Product;

/**
 * Whether the typology of a product calculates the Supplier commission at all
 * (spec 0202 D-6, spec 0306 D-7). The direction is NOT read here: it lives on
 * the commission rule (or on a manual commission) and is frozen on the line.
 */
final class SupplierCommissionEnablement
{
    /**
     * @param  array<int, int>  $productIds
     * @return array<int, bool> keyed by product id
     */
    public function enabledForProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->with('productTypology')
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => (bool) $product->productTypology?->supplier_commission_enabled,
            ])
            ->all();
    }

    public function enabledForProduct(int $productId): bool
    {
        return $this->enabledForProducts([$productId])[$productId] ?? false;
    }
}
