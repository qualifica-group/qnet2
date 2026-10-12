<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('costTypeUserWith')) {
    /**
     * A user holding exactly the given cost-types abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function costTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("cost-types.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("cost-types.{$ability}");
        }

        return $user;
    }
}
