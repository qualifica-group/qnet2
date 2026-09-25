<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('taskConfigActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskConfigActorWith(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('taskConfigStorePayload')) {
    /**
     * The minimum valid store payload for $resource: `task-statuses` is the
     * one configurator with two MANDATORY extra fields beyond name/color
     * (StoreTaskStatusRequest: `completion_percentage` and `group`, both
     * `required`).
     *
     * Canonicalised here (engineering.md §1.2): two former local copies
     * omitted `group`, relying on whichever file's copy happened to load
     * first in the run to carry the correct shape for every caller.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskConfigStorePayload(string $resource, array $overrides = []): array
    {
        $payload = ['name' => 'Nuova voce', 'color' => 'blue'];

        if ($resource === 'task-statuses') {
            $payload['completion_percentage'] = 40;
            $payload['group'] = 'open';
        }

        return [...$payload, ...$overrides];
    }
}
