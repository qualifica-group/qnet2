<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Activates or deactivates many products at once (spec 0366, D-2). Updates
 * model by model so each product gets its own activity-log entry, like the
 * other product bulk actions (ProductCostTypeService).
 */
class ProductActivationService
{
    /**
     * @param  array<int, int>  $productIds
     * @return array{updated: int}
     */
    public function apply(array $productIds, bool $isActive): array
    {
        $updated = 0;

        DB::transaction(function () use ($productIds, $isActive, &$updated): void {
            Product::query()->whereIn('id', $productIds)->get()->each(function (Product $product) use ($isActive, &$updated): void {
                $product->update(['is_active' => $isActive]);
                $updated++;
            });
        });

        return ['updated' => $updated];
    }
}
