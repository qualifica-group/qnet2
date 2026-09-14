<?php

use App\Authorization\TasksAuthorization;
use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Guards against the shared, file-duplicated `taskActorWith()` helper below:
// whichever copy loads FIRST across tests/Feature/Tasks wins (bare functions
// are global to the process), and an earlier-loaded copy may predate spec
// 0116 and not know these four permissions yet. Creating them here directly
// is idempotent and independent of which copy ends up active.
beforeEach(function () {
    foreach (['manageAll', 'complete', 'validate', 'block'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }
});

/*
|--------------------------------------------------------------------------
| Record-role matrix on `tasks` (spec 0116, MT-03)
|--------------------------------------------------------------------------
|
| Exercises the matrix TaskAbilityResolver innests into TaskPolicy and
| TasksAuthorization: the 17/6 protected/free field split (D-5), the
| manageAll-as-assignee deroga (D-2), and the AND-not-OR contract between
| the ability and the matrix on `permissions.actions`.
|
| AC-040's "POST /complete answers 403" half is ALSO asserted at the Gate
| level here (TaskActionsTest.php now carries the HTTP-level coverage of
| the six domain-action endpoints) — the Gate check exercises the exact
| same TaskPolicy::complete() the controller invokes, so it stays a useful,
| narrower proof of the AND-not-OR contract even with the endpoint in place.
*/

if (! function_exists('taskActorWith')) {
    /**
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
// AC-006 — an assignee sees the 17 protected fields readonly, the 6 free ones editable
// ---------------------------------------------------------------------------

it('AC-006: an assignee sees the protected fields readonly, the free fields editable and completion_date readonly', function () {
    $actor = taskActorWith(['view', 'update']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $fields = collect($this->getJson("/api/tasks/{$task->id}")->assertOk()->json('permissions.fields'));

    foreach (TaskAbilityResolver::PROTECTED_FIELDS as $protected) {
        expect($fields[$protected]['editable'])->toBeFalse("{$protected} should be readonly for a bare assignee")
            ->and($fields[$protected]['readonly'])->toBeTrue();
    }

    foreach (['description', 'task_status_id', 'start_time', 'end_time', 'closure_feedback'] as $free) {
        expect($fields[$free]['editable'])->toBeTrue("{$free} should stay editable for an assignee");
    }

    // REQUIREMENT CHANGED (spec 0127, D-2): completion_date is server-owned,
    // written only by the completion actions, so it is readonly for everyone.
    expect($fields['completion_date']['editable'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-008/AC-009 — the manageAll "gestore", and its admin-as-assignee deroga (D-2)
// ---------------------------------------------------------------------------

it('AC-008: an actor with tasks.manageAll unrelated to the Task may write a protected field', function () {
    $actor = taskActorWith(['update', 'manageAll']);
    $task = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Riassegnata dal gestore'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Riassegnata dal gestore');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Riassegnata dal gestore']);
});

it('AC-009: an actor with tasks.manageAll who is ALSO an assignee of that Task decays to plain assignee (D-2)', function () {
    $actor = taskActorWith(['update', 'manageAll']);
    $task = Task::factory()->create(['title' => 'Intatta']);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Non dovrebbe scrivere'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Intatta']);
});

// ---------------------------------------------------------------------------
// AC-002 (spec 0121) — requires_validation is protected: mandate-only on PATCH
// ---------------------------------------------------------------------------

it('AC-002 (spec 0121): an assignee sending requires_validation gets 422 and no write; the creator succeeds', function () {
    $assignee = taskActorWith(['view', 'update']);
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->patchJson("/api/tasks/{$task->id}", ['requires_validation' => true])
        ->assertStatus(422)->assertJsonValidationErrors('requires_validation');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'requires_validation' => false]);

    $creator = taskActorWith(['view', 'update']);
    $task2 = Task::factory()->forCreator($creator)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task2->id}", ['requires_validation' => true])
        ->assertOk()
        ->assertJsonPath('data.requires_validation', true);

    $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'requires_validation' => true]);
});

// REQUIREMENT CHANGED (spec 0126, D-4): actions() grows from 15 to 16 keys
// — `change_status` is appended LAST.
it('AC-015 (spec 0121): TasksAuthorization::actions() carries complete_to_validation, 16 keys total', function () {
    $actions = app(TasksAuthorization::class)->actions();

    expect($actions)->toHaveCount(16)
        ->and($actions)->toContain('complete_to_validation')
        ->and($actions)->toContain('close_via_status')
        ->and($actions)->toContain('create_subtask')
        ->and($actions)->toContain('change_status');
});

// ---------------------------------------------------------------------------
// AC-038/AC-039/AC-040 — permissions.actions, and the AND (not OR) of ability and matrix
// ---------------------------------------------------------------------------

it('AC-038: the creator of an open, unblocked Task sees complete/block true, approve/unblock false', function () {
    $actor = taskActorWith(['view', 'complete', 'block', 'validate']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete', true)
        ->assertJsonPath('permissions.actions.block', true)
        ->assertJsonPath('permissions.actions.approve', false)
        ->assertJsonPath('permissions.actions.unblock', false);
});

it('AC-039: a pure watcher sees all six domain-action flags false', function () {
    $actor = taskActorWith(['view', 'complete', 'block', 'validate']);
    $task = Task::factory()->create();
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tasks/{$task->id}")->assertOk();

    foreach (['complete', 'uncomplete', 'approve', 'reject', 'block', 'unblock'] as $action) {
        expect($response->json("permissions.actions.{$action}"))->toBeFalse("{$action} should be false for a pure watcher");
    }
});

it('AC-040: ability and matrix are ANDed, not ORed — a creator without tasks.complete gets a false flag and a denied Gate check', function () {
    // Deliberately NOT given `complete`: the actor is the creator (matrix
    // would allow it) but lacks the permission (ability would deny it).
    $actor = taskActorWith(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete', false);

    // HTTP-level coverage of POST /complete now lives in TaskActionsTest.php;
    // the Gate check below is a narrower, additional proof that it is the
    // exact same TaskPolicy::complete() the controller invokes.
    expect(Gate::forUser($actor)->denies('complete', $task))->toBeTrue();
});

it('AC-040 (converse): a creator WITH tasks.complete is granted by the same Gate check', function () {
    $actor = taskActorWith(['view', 'complete']);
    $task = Task::factory()->forCreator($actor)->create();

    expect(Gate::forUser($actor)->allows('complete', $task))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-014 (spec 0123, D-5) — permissions.actions.close_via_status
// ---------------------------------------------------------------------------

it('AC-014: close_via_status is false for a plain assignee on a Task requiring validation', function () {
    $assignee = taskActorWith(['view', 'update']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.close_via_status', false);
});

it('AC-014: close_via_status is false when requires_closure_feedback is on and the feedback is empty, even for the creator', function () {
    $creator = taskActorWith(['view', 'update']);
    $task = Task::factory()->requiringClosureFeedback()->forCreator($creator)->create();
    Sanctum::actingAs($creator);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.close_via_status', false);
});

it('AC-014: close_via_status is true for the creator on a Task with no obligations', function () {
    $creator = taskActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($creator)->create();
    Sanctum::actingAs($creator);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.close_via_status', true);
});

// ---------------------------------------------------------------------------
// AC-035 (spec 0123, D-9) — permissions.actions.create_subtask
// ---------------------------------------------------------------------------

it('AC-035: create_subtask is false on a blocked Task', function () {
    $actor = taskActorWith(['view', 'create']);
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.create_subtask', false);
});

it('AC-035: create_subtask is false on a Task in the in_validation phase', function () {
    $actor = taskActorWith(['view', 'create']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.create_subtask', false);
});

it('AC-035: create_subtask is false on a Task whose ancestor is frozen', function () {
    $actor = taskActorWith(['view', 'create']);
    $grandparent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $parent = Task::factory()->forCreator($actor)->childOf($grandparent)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()->assertJsonPath('permissions.actions.create_subtask', false);
});

it('AC-035: create_subtask is true on an open Task for an actor holding tasks.create', function () {
    $actor = taskActorWith(['view', 'create']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.create_subtask', true);
});
