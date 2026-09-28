<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Spatie\Permission\Models\Permission;

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

if (! function_exists('quoteTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function quoteTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("quotes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("quotes.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('quoteHttpRevenueProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (D-7), so a REVENUE line never trips the 422 coverage guard
     * (QuoteCoverageTest owns AC-050/051 directly).
     */
    function quoteHttpRevenueProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}
