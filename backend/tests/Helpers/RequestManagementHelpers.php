<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('requestManagementUserWith')) {
    /**
     * Canonicalised here (engineering.md §1.2): the former local copies
     * disagreed on the ability catalogue itself — four files never created
     * `request-management.transferContact`, one never created
     * `create`/`delete`/`import` — so whichever file's copy loaded first
     * decided, for the WHOLE run, which abilities the OTHER files' actors
     * could even be granted. The set below is the union of every ability
     * any caller in this directory requests.
     *
     * @param  array<int, string>  $abilities
     */
    function requestManagementUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'transferContact'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}
