<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('emailTemplateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function emailTemplateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("email-templates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("email-templates.{$ability}");
        }

        return $user;
    }
}
