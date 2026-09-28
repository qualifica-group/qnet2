<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}
