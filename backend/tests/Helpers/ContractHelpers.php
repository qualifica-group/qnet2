<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;

if (! function_exists('contractLifecycleRevenueProduct')) {
    /**
     * Spec 0102: POST /api/quotes now requires at least one offer_lines row.
     * A category with an EFFECTIVE business function so the auto-add
     * coverage path never trips the 422 guard (OpportunityProductLineCoverage).
     */
    function contractLifecycleRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}
