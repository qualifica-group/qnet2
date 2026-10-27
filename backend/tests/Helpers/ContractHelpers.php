<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Spatie\Permission\Models\Permission;

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

if (! function_exists('contractScopedUserWith')) {
    /**
     * An actor holding the given `contracts.*` abilities (spec 0316). Tests
     * that are not about record visibility add `viewAll` to keep seeing
     * every contract (REQUIREMENT CHANGED D-1).
     *
     * @param  array<int, string>  $abilities
     */
    function contractScopedUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'update', 'export', 'viewActivity', 'validate', 'terminate', 'program', 'changeStatus', 'reactivate', 'viewSite', 'viewAll'] as $ability) {
            Permission::findOrCreate("contracts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("contracts.{$ability}");
        }

        return $user;
    }
}
