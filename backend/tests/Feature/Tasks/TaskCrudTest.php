<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task CRUD (spec 0101, AC-010..AC-017; spec 0118 D-1/D-2, AC-001..AC-008)
|--------------------------------------------------------------------------
|
| The visibility scoping (D-9) has its own suite: every actor here is given
| `tasks.viewAll` on top of the requested abilities so a 403 in THIS file
| always means "missing resource permission", never "not a member" — the
| same separation WorkOrderSecurityTest/WorkOrderVisibilityTest draw.
|
| The status-derivation feature tests (spec 0118 AC-009..AC-015) live in
| TaskInitialStatusTest.php instead, next to the resolver's own unit tests
| and the "Assegnato" promotion — the theme that file already owns.
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

if (! function_exists('taskPayload')) {
    /**
     * The four create-mandatory fields (spec 0118 D-1, data_contract POST
     * /api/tasks): `title`, `requester_id`, `assignee_ids` (min 1) and
     * `end_date`. `task_status_id` is DELIBERATELY absent — it is
     * `prohibited` since D-3, the server derives it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Prima attivita',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

// ---------------------------------------------------------------------------
// AC-001..AC-005 — the four create-mandatory fields (spec 0118 D-1)
// ---------------------------------------------------------------------------

it('AC-001: POST without requester_id is 422 on requester_id, no row created', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = taskPayload();
    unset($payload['requester_id']);

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('requester_id');

    $this->assertDatabaseMissing('tasks', ['title' => 'Prima attivita']);
});

it('AC-002: POST without assignee_ids is 422 on assignee_ids', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = taskPayload();
    unset($payload['assignee_ids']);

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('assignee_ids');
});

it('AC-003: POST with assignee_ids: [] is 422 on assignee_ids (min 1)', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['assignee_ids' => []]))
        ->assertStatus(422)->assertJsonValidationErrors('assignee_ids');
});

it('AC-004: POST without end_date is 422 on end_date', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = taskPayload();
    unset($payload['end_date']);

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('end_date');
});

it('AC-005: POST without start_date is 201: start_date stays optional', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload())->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-006..AC-008 — the same four fields on PATCH (spec 0118 D-2)
// ---------------------------------------------------------------------------

it('AC-006: PATCH with requester_id: null is 422 and the Task does not change', function () {
    $actor = taskActorWith(['view', 'update']);
    $requester = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['requester_id' => $requester->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['requester_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors('requester_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'requester_id' => $requester->id]);
});

it('AC-007: PATCH submitting only description is 200: the four mandatory fields are not required when absent', function () {
    $actor = taskActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['description' => 'Aggiornamento minimo'])
        ->assertOk()
        ->assertJsonPath('data.description', 'Aggiornamento minimo');
});

it('AC-008: PATCH of title alone on a historical Task without requester_id is 200: no retroactive sanitisation', function () {
    $actor = taskActorWith(['view', 'update']);
    // A historical row, built directly rather than through the endpoint —
    // exactly the shape D-2 says is never migrated or backfilled.
    $task = Task::factory()->forCreator($actor)->create(['requester_id' => null]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Solo il titolo'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Solo il titolo');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'requester_id' => null, 'title' => 'Solo il titolo']);
});

// ---------------------------------------------------------------------------
// AC-010 — create, with the creator taken from the authenticated actor
// ---------------------------------------------------------------------------

it('AC-010: POST with the four mandatory fields returns 201 and persists the row', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload(['title' => 'Chiamare il cliente']))
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.title', 'Chiamare il cliente');

    $this->assertDatabaseHas('tasks', ['title' => 'Chiamare il cliente', 'id' => $response->json('data.id')]);
});

it('AC-010: creator_id is the authenticated actor, never taken from the payload (D-10)', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', taskPayload())->assertCreated();

    $this->assertDatabaseHas('tasks', ['id' => $response->json('data.id'), 'creator_id' => $actor->id]);
    expect($response->json('data.creator.id'))->toBe($actor->id);
});

it('AC-010: 422 when title is missing', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = taskPayload();
    unset($payload['title']);

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('title');
});

it('AC-009: POST with task_status_id among the payload is 422 (prohibited), no row created', function () {
    $actor = taskActorWith(['create']);
    $status = TaskStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['task_status_id' => $status->id]))
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

    $this->assertDatabaseMissing('tasks', ['title' => 'Prima attivita']);
});

// ---------------------------------------------------------------------------
// AC-011 — creator_id and completion_percentage are prohibited, for everyone
// ---------------------------------------------------------------------------

it('AC-011: POST with creator_id in the payload is 422, even for a super-admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));
    $victim = User::factory()->create();
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/tasks', taskPayload(['creator_id' => $victim->id]))
        ->assertStatus(422)->assertJsonValidationErrors('creator_id');

    $this->assertDatabaseMissing('tasks', ['creator_id' => $victim->id]);
});

it('AC-011: POST with completion_percentage in the payload is 422, even for a super-admin (D-6)', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/tasks', taskPayload(['completion_percentage' => 90]))
        ->assertStatus(422)->assertJsonValidationErrors('completion_percentage');
});

it('AC-011: POST with is_blocked in the payload is 422, even for a super-admin (spec 0116 D-6)', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));
    Sanctum::actingAs($superAdmin);

    $this->postJson('/api/tasks', taskPayload(['is_blocked' => true]))
        ->assertStatus(422)->assertJsonValidationErrors('is_blocked');
});

it('AC-011: PATCH cannot reassign creator_id: 422 and the original creator stands', function () {
    $actor = taskActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['creator_id' => User::factory()->create()->id])
        ->assertStatus(422)->assertJsonValidationErrors('creator_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'creator_id' => $actor->id]);
});

// ---------------------------------------------------------------------------
// AC-012 — partial PATCH leaves the two user pivots alone
// ---------------------------------------------------------------------------

it('AC-012: PATCH of the title alone touches neither assignees nor watchers', function () {
    $actor = taskActorWith(['view', 'update']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['title' => 'Prima']);
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Dopo'])->assertOk();

    expect($task->fresh()->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($task->fresh()->watchers->pluck('id')->all())->toBe([$watcher->id]);
});

// REQUIREMENT CHANGED (spec 0118 D-2): assignee_ids keeps its `min:1` floor
// on PATCH too — a Task can no longer be emptied of every assignee, on
// create or on update. The AC-012 scenario "assignee_ids: [] empties the
// assignees" from spec 0101 no longer holds; this is its replacement.
it('AC-012 (spec 0118 D-2): PATCH with assignee_ids: [] is 422, and neither pivot changes', function () {
    $actor = taskActorWith(['view', 'update']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => []])
        ->assertStatus(422)->assertJsonValidationErrors('assignee_ids');

    expect($task->fresh()->assignees->pluck('id')->all())->toBe([$assignee->id])
        ->and($task->fresh()->watchers->pluck('id')->all())->toBe([$watcher->id]);
});

it('AC-012: assignee_ids full-replaces rather than appending', function () {
    $actor = taskActorWith(['view', 'update']);
    $first = User::factory()->create();
    $second = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($first->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [$second->id]])->assertOk();

    expect($task->fresh()->assignees->pluck('id')->all())->toBe([$second->id]);
});

// ---------------------------------------------------------------------------
// AC-013 — the hierarchy guard (D-12)
// ---------------------------------------------------------------------------

it('AC-013: PATCH setting parent_task_id to the task itself is 422 and nothing persists', function () {
    $actor = taskActorWith(['view', 'update']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['parent_task_id' => $task->id])
        ->assertStatus(422)->assertJsonValidationErrors('parent_task_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'parent_task_id' => null]);
});

it('AC-013: PATCH that would close a cycle A->B->A is 422 and nothing persists', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create(['title' => 'A']);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'B']);
    Sanctum::actingAs($actor);

    // A would become a child of B, while B is already a child of A.
    $this->patchJson("/api/tasks/{$parent->id}", ['parent_task_id' => $child->id])
        ->assertStatus(422)->assertJsonValidationErrors('parent_task_id');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'parent_task_id' => null])
        ->assertDatabaseHas('tasks', ['id' => $child->id, 'parent_task_id' => $parent->id]);
});

it('AC-013: a longer cycle A->B->C->A is rejected too: the guard walks the whole chain', function () {
    $actor = taskActorWith(['view', 'update']);
    $a = Task::factory()->forCreator($actor)->create(['title' => 'A']);
    $b = Task::factory()->forCreator($actor)->childOf($a)->create(['title' => 'B']);
    $c = Task::factory()->forCreator($actor)->childOf($b)->create(['title' => 'C']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$a->id}", ['parent_task_id' => $c->id])
        ->assertStatus(422)->assertJsonValidationErrors('parent_task_id');

    $this->assertDatabaseHas('tasks', ['id' => $a->id, 'parent_task_id' => null]);
});

it('AC-013: a legitimate parent is accepted, depth is not limited (D-12)', function () {
    $actor = taskActorWith(['view', 'update', 'create']);
    $grandparent = Task::factory()->forCreator($actor)->create();
    $parent = Task::factory()->forCreator($actor)->childOf($grandparent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['parent_task_id' => $parent->id]))->assertCreated();
});

// ---------------------------------------------------------------------------
// AC-014 — referent must belong to the submitted registry
// ---------------------------------------------------------------------------

it('AC-014: POST with a referent_id not linked to the submitted registry_id is 422 on referent_id', function () {
    $actor = taskActorWith(['create']);
    $registry = Registry::factory()->create();
    $foreignReferent = Referent::factory()->create();
    $foreignReferent->registries()->attach(Registry::factory()->create()->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['registry_id' => $registry->id, 'referent_id' => $foreignReferent->id]))
        ->assertStatus(422)->assertJsonValidationErrors('referent_id');
});

it('AC-014: POST with a referent_id linked to the submitted registry_id is accepted', function () {
    $actor = taskActorWith(['create', 'view']);
    $registry = Registry::factory()->create();
    $referent = Referent::factory()->create();
    $referent->registries()->attach($registry->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['registry_id' => $registry->id, 'referent_id' => $referent->id]))
        ->assertCreated()
        ->assertJsonPath('data.referent_id', $referent->id);
});
