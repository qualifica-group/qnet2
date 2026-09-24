<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The cascade delete guard (spec 0153, D-5, REQUIREMENT CHANGED)
|--------------------------------------------------------------------------
|
| Split out of TaskCrudTest to keep both files under the 300-line soft limit
| (engineering.md §6). Was: a Task with children could not be deleted at all
| (409). Now: deleting a Task deletes its WHOLE sub-tree in one transaction,
| unless a descendant is itself not deletable by the actor (role or state),
| in which case NOTHING is deleted (422, `errors.task` names the offending
| descendant) — through the single endpoint OR the generic bulk-delete alike.
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
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
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

// REQUIREMENT CHANGED (spec 0153, D-5): a parent with a deletable sub-task
// (same actor role, open/unblocked) now cascades — both rows are gone.
it('AC-015 (spec 0153, D-5): DELETE of a task with a deletable sub-task removes both parent and child', function () {
    $actor = taskActorWith(['view', 'delete']);
    $parent = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$parent->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $parent->id])
        ->assertDatabaseMissing('tasks', ['id' => $child->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-5): a sub-task the actor may NOT delete
// (no role of theirs on it) aborts the WHOLE cascade — 422 naming it,
// neither row removed.
it('AC-007 (spec 0153): DELETE of a task with a sub-task the actor cannot delete is 422, neither row removed', function () {
    $actor = taskActorWith(['view', 'delete']);
    $parent = Task::factory()->forCreator($actor)->create();
    $foreignChild = Task::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('task');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id])
        ->assertDatabaseHas('tasks', ['id' => $foreignChild->id]);
});

it('AC-016: the generic bulk-delete applies the same cascade', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete']);
    $withChild = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->forCreator($actor)->childOf($withChild)->create();
    $free = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/bulk-delete', ['ids' => [$withChild->id, $free->id]]);

    $this->assertDatabaseMissing('tasks', ['id' => $withChild->id])
        ->assertDatabaseMissing('tasks', ['id' => $child->id])
        ->assertDatabaseMissing('tasks', ['id' => $free->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-5): the descendant CASCADE (not a mere
// count) ignores the visibility scope: an invisible sub-task the actor holds
// no role on still blocks the delete (422), never a silent 409/count-only
// check — the constraint is about the data, not about who is looking.
it('AC-017: the descendant cascade ignores the visibility scope: an invisible sub-task still blocks the delete', function () {
    $actor = taskActorWith(['view', 'delete'], withViewAll: false);
    $parent = Task::factory()->forCreator($actor)->create();
    $invisibleChild = Task::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$parent->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('task');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id])
        ->assertDatabaseHas('tasks', ['id' => $invisibleChild->id]);
});
