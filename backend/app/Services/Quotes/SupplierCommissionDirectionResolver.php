<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\SupplierCommissionDirection;
use App\Models\Product;

/**
 * The Supplier commission direction a product currently carries through its
 * typology (spec 0202, D-7): null when the typology has the calculation
 * disabled. Read at ONE moment only — when a REVENUE line is created or its
 * product changes — and then frozen on the line (D-3).
 */
final class SupplierCommissionDirectionResolver
{
    /**
     * @param  array<int, int>  $productIds
     * @return array<int, SupplierCommissionDirection|null> keyed by product id
     */
    public function forProducts(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        return Product::query()
            ->with('productTypology')
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->mapWithKeys(fn (Product $product): array => [
                $product->id => $product->productTypology?->supplier_commission_enabled
                    ? $product->productTypology->supplier_commission_direction
                    : null,
            ])
            ->all();
    }

    public function forProduct(int $productId): ?SupplierCommissionDirection
    {
        return $this->forProducts([$productId])[$productId] ?? null;
    }
}
