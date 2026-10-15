<?php

declare(strict_types=1);

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
