<?php

declare(strict_types=1);

use App\Models\TaskType;
use App\Models\User;
use Spatie\Permission\Models\Permission;

if (! function_exists('validTimeEntryPayload')) {
    /**
     * A minimum-valid segnatempo payload, spread with $overrides. The exact
     * date/minutes are arbitrary fixture values no assertion depends on.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validTimeEntryPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-14',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
        ], $overrides);
    }
}

if (! function_exists('taskCompletionActorWith')) {
    /**
     * An actor with `tasks.viewAll` plus the given `tasks`/`time-entries`
     * abilities. $timeEntryAbilities defaults to none: most callers only
     * need the `tasks.*` set.
     *
     * Canonicalised here (engineering.md §1.2): the two former local copies
     * diverged on this exact param — one file never needed the
     * `time-entries.*` catalogue/param at all — but the 2-param shape is a
     * strict superset compatible with every 1-arg call site.
     *
     * @param  array<int, string>  $taskAbilities
     * @param  array<int, string>  $timeEntryAbilities
     */
    function taskCompletionActorWith(array $taskAbilities, array $timeEntryAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.viewAll');

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }
        foreach ($timeEntryAbilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}
