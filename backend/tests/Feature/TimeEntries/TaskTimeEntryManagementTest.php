<?php

use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task-linked segnatempo follow the Task role matrix (spec 0126, D-3, AC-006/AC-007)
|--------------------------------------------------------------------------
|
| Spec 0153, D-9 (REQUIREMENT CHANGED): canManageTimeEntry() dropped the
| ownership branch entirely — it is now `canUpdate($actor, $task)` alone, so
| any assignee manages ANY segnatempo of the Task, not only their own.
*/

if (! function_exists('taskTimeEntryManagementActor')) {
    /**
     * An actor holding $timeEntryAbilities on `time-entries`, plus
     * `tasks.view` (D-9's own resource-level gate on the Task-scoped list).
     *
     * @param  array<int, string>  $timeEntryAbilities
     */
    function taskTimeEntryManagementActor(array $timeEntryAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }
        Permission::findOrCreate('tasks.view');

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.view');

        foreach ($timeEntryAbilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('assertEntryPermissionsOnTaskList')) {
    /**
     * Reads `permissions.update/delete` for $entry via the Task-scoped list
     * (D-9): unlike GET /api/time-entries/{id} (TimeEntryPolicy::view, out
     * of D-3's scope, unchanged), the list does not gate on record-level
     * ownership — any actor with `tasks.view` in scope of $task sees every
     * entry, with the write flags computed per-viewer.
     */
    function assertEntryPermissionsOnTaskList(TestCase $test, Task $task, TimeEntry $entry, bool $expectedUpdate, bool $expectedDelete): void
    {
        $response = $test->getJson("/api/tasks/{$task->id}/time-entries")->assertOk();
        $item = collect($response->json('data.items'))->firstWhere('id', $entry->id);

        expect($item)->not->toBeNull()
            ->and($item['permissions']['update'])->toBe($expectedUpdate)
            ->and($item['permissions']['delete'])->toBe($expectedDelete);
    }
}

// ---------------------------------------------------------------------------
// AC-006 — segnatempo of a Task, inserted by assignee A
// ---------------------------------------------------------------------------

it('AC-006: the entry owner, an assignee, may PUT and DELETE their own entry', function () {
    $creator = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create();
    $assigneeA = taskTimeEntryManagementActor(['viewAny', 'view', 'update', 'delete']);
    $task->assignees()->attach($assigneeA->id);
    $entry = TimeEntry::factory()->forUser($assigneeA)->create(['task_id' => $task->id]);
    Sanctum::actingAs($assigneeA);

    assertEntryPermissionsOnTaskList($this, $task, $entry, true, true);

    // task_id is resubmitted (D-5's full-replace PUT): the entry stays a
    // Task-linked segnatempo through the update, so the DELETE right after
    // still exercises the D-3 matrix rather than the taskless fallback.
    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'task_id' => $task->id, 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertOk();

    $this->deleteJson("/api/time-entries/{$entry->id}")->assertOk();
    $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
});

it('AC-006: the Task creator (mandate owner) may PUT and DELETE the assignee\'s entry', function () {
    $creator = taskTimeEntryManagementActor(['viewAny', 'view', 'update', 'delete']);
    $task = Task::factory()->forCreator($creator)->create();
    $assigneeA = User::factory()->create();
    $task->assignees()->attach($assigneeA->id);
    $entry = TimeEntry::factory()->forUser($assigneeA)->create(['task_id' => $task->id]);
    Sanctum::actingAs($creator);

    assertEntryPermissionsOnTaskList($this, $task, $entry, true, true);

    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'task_id' => $task->id, 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertOk();

    $this->deleteJson("/api/time-entries/{$entry->id}")->assertOk();
    $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-9): canManageTimeEntry() no longer
// requires ownership of the entry — a second assignee B may now PUT/DELETE
// assignee A's entry on the same Task, since canUpdate() admits any
// assignee (AC-012 of spec 0153).
it('AC-006 (spec 0153, D-9): a second assignee B (not the owner) may PUT and DELETE assignee A\'s entry', function () {
    $task = Task::factory()->create();
    $assigneeA = User::factory()->create();
    $task->assignees()->attach($assigneeA->id);
    $assigneeB = taskTimeEntryManagementActor(['viewAny', 'view', 'update', 'delete']);
    $task->assignees()->attach($assigneeB->id);
    $entry = TimeEntry::factory()->forUser($assigneeA)->create(['task_id' => $task->id]);
    Sanctum::actingAs($assigneeB);

    assertEntryPermissionsOnTaskList($this, $task, $entry, true, true);

    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'task_id' => $task->id, 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertOk();
    $this->deleteJson("/api/time-entries/{$entry->id}")->assertOk();
    $this->assertDatabaseMissing('time_entries', ['id' => $entry->id]);
});

it('AC-006: a watcher, owner of their own entry on the Task, gets 403 on PUT/DELETE', function () {
    $task = Task::factory()->create();
    $watcher = taskTimeEntryManagementActor(['viewAny', 'view', 'update', 'delete']);
    $task->watchers()->attach($watcher->id);
    $entry = TimeEntry::factory()->forUser($watcher)->create(['task_id' => $task->id]);
    Sanctum::actingAs($watcher);

    assertEntryPermissionsOnTaskList($this, $task, $entry, false, false);

    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'title' => 'X', 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertForbidden();
    $this->deleteJson("/api/time-entries/{$entry->id}")->assertForbidden();
    $this->assertDatabaseHas('time_entries', ['id' => $entry->id]);
});

it('AC-006: time-entries.manageAll without any role on the Task gets 403 on PUT/DELETE', function () {
    $task = Task::factory()->create();
    $assigneeA = User::factory()->create();
    $task->assignees()->attach($assigneeA->id);
    $entry = TimeEntry::factory()->forUser($assigneeA)->create(['task_id' => $task->id]);
    $admin = taskTimeEntryManagementActor(['viewAny', 'view', 'update', 'delete', 'manageAll']);
    Permission::findOrCreate('tasks.viewAll');
    $admin->givePermissionTo('tasks.viewAll');
    Sanctum::actingAs($admin);

    assertEntryPermissionsOnTaskList($this, $task, $entry, false, false);

    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'title' => 'X', 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertForbidden();
    $this->deleteJson("/api/time-entries/{$entry->id}")->assertForbidden();
    $this->assertDatabaseHas('time_entries', ['id' => $entry->id]);
});

// ---------------------------------------------------------------------------
// AC-007 — segnatempo without a Task: unchanged
// ---------------------------------------------------------------------------

it('AC-007: without a Task, the owner may still PUT/DELETE their own entry', function () {
    $owner = taskTimeEntryManagementActor(['view', 'update', 'delete']);
    $entry = TimeEntry::factory()->forUser($owner)->create(['minutes' => 30]);
    Sanctum::actingAs($owner);

    $this->putJson("/api/time-entries/{$entry->id}", ['date' => $entry->date->format('Y-m-d'), 'title' => 'X', 'task_type_id' => $entry->task_type_id, 'minutes' => 45])
        ->assertOk();
    $this->deleteJson("/api/time-entries/{$entry->id}")->assertOk();
});

it('AC-007: without a Task, time-entries.manageAll may still PUT/DELETE someone else\'s entry', function () {
    $owner = User::factory()->create();
    $admin = taskTimeEntryManagementActor(['view', 'update', 'delete', 'manageAll']);
    $entryForUpdate = TimeEntry::factory()->forUser($owner)->create(['minutes' => 30]);
    $entryForDelete = TimeEntry::factory()->forUser($owner)->create(['minutes' => 30]);
    Sanctum::actingAs($admin);

    $this->putJson("/api/time-entries/{$entryForUpdate->id}", ['date' => $entryForUpdate->date->format('Y-m-d'), 'title' => 'X', 'task_type_id' => $entryForUpdate->task_type_id, 'minutes' => 45])
        ->assertOk();
    $this->deleteJson("/api/time-entries/{$entryForDelete->id}")->assertOk();
});
