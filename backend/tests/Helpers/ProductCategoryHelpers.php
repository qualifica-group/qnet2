<?php

declare(strict_types=1);

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\ProductCategory;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('productCategoryUserWith')) {
    /**
     * A user holding only the given `product-categories.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function productCategoryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("product-categories.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("product-categories.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('usageAreaActor')) {
    /**
     * Spec 0389: a user holding exactly the given fully qualified permissions.
     *
     * @param  array<int, string>  $permissions
     */
    function usageAreaActor(array $permissions): User
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }
}

if (! function_exists('usageAreaCategory')) {
    /** Spec 0389: a classifiable root category used in $area. */
    function usageAreaCategory(string $area, string $name = 'Category'): ProductCategory
    {
        return ProductCategory::factory()->create([
            'name' => $name,
            'usage_area' => $area,
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);
    }
}

if (! function_exists('usageAreaOpportunity')) {
    /** Spec 0389: an opportunity classified with every given category. */
    function usageAreaOpportunity(ProductCategory ...$categories): Opportunity
    {
        $opportunity = Opportunity::factory()->create();

        foreach ($categories as $category) {
            OpportunityProductLine::factory()->for($opportunity)->create([
                'business_function_id' => $category->business_function_id,
                'product_category_id' => $category->id,
            ]);
        }

        return $opportunity;
    }
}
