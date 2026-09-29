<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('documentBundleUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentBundleUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-bundles.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-bundles.{$ability}");
        }

        return $user;
    }
}
