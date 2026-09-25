<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;

if (! function_exists('revenueLineProduct')) {
    /**
     * A Product under a coherent business-function/category pair (spec 0023
     * REV), with an explicit or freshly factory-made unit of measure —
     * $unitOfMeasure lets a caller freeze/compare a SPECIFIC one.
     */
    function revenueLineProduct(?UnitOfMeasure $unitOfMeasure = null): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_of_measure_id' => $unitOfMeasure?->id ?? UnitOfMeasure::factory()->create()->id,
        ]);
    }
}
