<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

if (! function_exists('hrCatalogUserWith')) {
    /**
     * A user holding exactly the given abilities on an HR catalogue resource
     * (`job-roles` or `job-activities`, spec 0271).
     *
     * @param  array<int, string>  $abilities
     */
    function hrCatalogUserWith(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}", 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}
