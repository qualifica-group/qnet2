<?php

namespace App\Services\Products;

use App\Jobs\ClassifyRevenueLinesJob;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Mass assignment of the default revenue sub-account on products (spec 0350).
 * Updates model by model so each product gets its own activity-log entry, then
 * queues the classification of the still unclassified lines of the products
 * whose value changed (D-9b).
 */
class ProductRevenueTypeService
{
    /**
     * @param  array<int, int>  $productIds
     * @return array{updated: int}
     */
    public function assign(array $productIds, ?int $revenueTypeId): array
    {
        $updated = 0;
        $changedIds = [];

        DB::transaction(function () use ($productIds, $revenueTypeId, &$updated, &$changedIds): void {
            Product::query()->whereIn('id', $productIds)->get()->each(function (Product $product) use ($revenueTypeId, &$updated, &$changedIds): void {
                $product->update(['revenue_type_id' => $revenueTypeId]);
                $updated++;

                if ($product->wasChanged('revenue_type_id')) {
                    $changedIds[] = $product->id;
                }
            });
        });

        if ($changedIds !== []) {
            ClassifyRevenueLinesJob::dispatch($changedIds);
        }

        return ['updated' => $updated];
    }
}
