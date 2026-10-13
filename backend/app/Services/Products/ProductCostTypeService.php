<?php

namespace App\Services\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Mass assignment of the default cost type on products (spec 0251, D-6).
 * Updates model by model so each product gets its own activity-log entry.
 */
class ProductCostTypeService
{
    /**
     * @param  array<int, int>  $productIds
     * @return array{updated: int}
     */
    public function assign(array $productIds, ?int $costTypeId): array
    {
        $updated = DB::transaction(function () use ($productIds, $costTypeId): int {
            $updated = 0;

            Product::query()->whereIn('id', $productIds)->get()->each(function (Product $product) use ($costTypeId, &$updated): void {
                $product->update(['cost_type_id' => $costTypeId]);
                $updated++;
            });

            return $updated;
        });

        return ['updated' => $updated];
    }
}
