<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The sub-task delete guard (spec 0101, D-8a, AC-015..AC-017)
|--------------------------------------------------------------------------
|
| Split out of TaskCrudTest to keep both files under the 300-line soft limit
| (engineering.md §6). One concern: a Task with children cannot be deleted,
| through the single endpoint OR through the generic bulk-delete, and the
| child count is a fact about the data rather than about who is looking.
*/

if (! function_exists('taskActorWith')) {
    /**
     * An actor holding $abilities on `tasks`. `viewAll` is granted on top by
     * default so a 403 in a suite that is NOT about the membership scoping
     * always means "missing resource permission" — the separation
     * WorkOrderSecurityTest/WorkOrderVisibilityTest already draw. Pass
     * `withViewAll: false` to exercise the scope itself.
     *
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
     *
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-015 / AC-016 / AC-017 — the sub-task delete guard (D-8a)
// ---------------------------------------------------------------------------

it('AC-015: DELETE of a task with no children returns 204 and removes the row', function () {
    $actor = taskActorWith(['view', 'delete']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('AC-015: DELETE of a task with a sub-task is 409, and neither parent nor child is removed', function () {
    $actor = taskActorWith(['view', 'delete']);
    $parent = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$parent->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This task has sub-tasks and cannot be deleted.');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id])
        ->assertDatabaseHas('tasks', ['id' => $child->id]);
});

it('AC-016: the generic bulk-delete applies the same guard', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete']);
    $withChild = Task::factory()->forCreator($actor)->create();
    Task::factory()->forCreator($actor)->childOf($withChild)->create();
    $free = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/bulk-delete', ['ids' => [$withChild->id, $free->id]]);

    $this->assertDatabaseHas('tasks', ['id' => $withChild->id])
        ->assertDatabaseMissing('tasks', ['id' => $free->id]);
});

it('AC-017: the child count ignores the visibility scope: an invisible sub-task still blocks the delete', function () {
    // No `viewAll`: the actor sees their own parent but NOT the sub-task,
    // which belongs to somebody else entirely. The constraint is about the
    // data, not about who is looking (D-8a).
    $actor = taskActorWith(['view', 'delete'], withViewAll: false);
    $parent = Task::factory()->forCreator($actor)->create();
    $invisibleChild = Task::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$parent->id}")->assertStatus(409);

    $this->assertDatabaseHas('tasks', ['id' => $parent->id])
        ->assertDatabaseHas('tasks', ['id' => $invisibleChild->id]);
});
