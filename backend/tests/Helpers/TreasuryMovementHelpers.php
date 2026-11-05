<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('treasuryUserWith')) {
    /**
     * A user holding exactly the given treasury-movements abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function treasuryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'viewActivity', 'reconcile'] as $ability) {
            Permission::findOrCreate("treasury-movements.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("treasury-movements.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('treasuryWriter')) {
    function treasuryWriter(): User
    {
        return treasuryUserWith(['viewAny', 'view', 'create', 'update', 'delete', 'viewActivity']);
    }
}
