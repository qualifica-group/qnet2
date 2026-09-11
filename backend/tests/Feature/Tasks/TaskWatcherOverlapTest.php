<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Watcher overlap guard (spec 0118, D-9, AC-028..AC-034)
|--------------------------------------------------------------------------
|
| An id in `watcher_ids` may not also be the creator, the requester or an
| assignee of the same Task, on POST and on PATCH alike. This formally
| retires AC-083 of spec 0101 ("an assignee and a watcher may be the same
| person") that spec 0116 D-3 had suspended in that very name — see
| TaskWatcherOverlapGuard's docblock and TaskRecordRolesTest/TaskSchemaTest
| for the two places that pinned it and are updated in this same change.
|
| AC-033 is the load-bearing case: the rule is judged on the RESULTING
| assignee/watcher sets, not on the submitted keys alone, so a PATCH that
| moves someone INTO `assignee_ids` without resubmitting `watcher_ids` is
| still refused when that person is already a persisted watcher.
|
| Every 422 here is paired with an assertion that neither pivot moved: a
| guard that rejects the response but still commits the sync would satisfy
| the status code alone.
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
// AC-028..AC-031 — POST
// ---------------------------------------------------------------------------

it('AC-028: POST with watcher_ids containing the creator is 422 on watcher_ids, no row created', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Overlap con creatore',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'end_date' => '2026-12-31',
        'watcher_ids' => [$actor->id],
    ])->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    $this->assertDatabaseMissing('tasks', ['title' => 'Overlap con creatore']);
});

it('AC-029: POST with watcher_ids containing the submitted requester_id is 422 on watcher_ids', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Overlap con richiedente',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'end_date' => '2026-12-31',
        'watcher_ids' => [$requester->id],
    ])->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    $this->assertDatabaseMissing('tasks', ['title' => 'Overlap con richiedente']);
});

it('AC-030: POST with the same id in assignee_ids and watcher_ids is 422 on watcher_ids', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $both = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Overlap con assegnatario',
        'requester_id' => $requester->id,
        'assignee_ids' => [$both->id],
        'end_date' => '2026-12-31',
        'watcher_ids' => [$both->id],
    ])->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    $this->assertDatabaseMissing('tasks', ['title' => 'Overlap con assegnatario']);
});

it('AC-031: POST with watchers distinct from creator, requester and assignees is 201', function () {
    $actor = taskActorWith(['create', 'view']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks', [
        'title' => 'Nessuna sovrapposizione',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'end_date' => '2026-12-31',
        'watcher_ids' => [$watcher->id],
    ])->assertCreated();

    $taskId = $response->json('data.id');
    $this->assertDatabaseHas('task_watcher', ['task_id' => $taskId, 'user_id' => $watcher->id]);
});

// ---------------------------------------------------------------------------
// AC-032/AC-033 — PATCH, evaluated on the RESULTING pair
// ---------------------------------------------------------------------------

it('AC-032: PATCH watcher_ids with an existing assignee is 422, and neither pivot changes', function () {
    $actor = taskActorWith(['view', 'update']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['requester_id' => $requester->id]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['watcher_ids' => [$assignee->id]])
        ->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    $this->assertDatabaseHas('task_assignee', ['task_id' => $task->id, 'user_id' => $assignee->id]);
    $this->assertDatabaseMissing('task_watcher', ['task_id' => $task->id, 'user_id' => $assignee->id]);
});

it('AC-033: PATCH assignee_ids with an existing watcher, without submitting watcher_ids, is 422 on the RESULTING pair', function () {
    $actor = taskActorWith(['view', 'update']);
    $requester = User::factory()->create();
    $currentAssignee = User::factory()->create();
    $watcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['requester_id' => $requester->id]);
    $task->assignees()->attach($currentAssignee->id);
    $task->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['assignee_ids' => [$watcher->id]])
        ->assertStatus(422)->assertJsonValidationErrors('watcher_ids');

    $this->assertDatabaseHas('task_assignee', ['task_id' => $task->id, 'user_id' => $currentAssignee->id]);
    $this->assertDatabaseMissing('task_assignee', ['task_id' => $task->id, 'user_id' => $watcher->id]);
    $this->assertDatabaseHas('task_watcher', ['task_id' => $task->id, 'user_id' => $watcher->id]);
});

// ---------------------------------------------------------------------------
// AC-034 — the roles that still sum: creator and requester may coincide
// ---------------------------------------------------------------------------

it('AC-034: POST where the actor is both creator and requester still succeeds — only the watcher slot is exclusive (D-9)', function () {
    $actor = taskActorWith(['create', 'view']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Creatore e richiedente coincidono',
        'requester_id' => $actor->id,
        'assignee_ids' => [$assignee->id],
        'end_date' => '2026-12-31',
        'watcher_ids' => [$watcher->id],
    ])->assertCreated()
        ->assertJsonPath('data.requester_id', $actor->id);
});
