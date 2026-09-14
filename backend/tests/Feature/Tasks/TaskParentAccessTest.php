<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Attaching a Task under a parent (spec 0125, D-3/D-4)
|--------------------------------------------------------------------------
|
| A parent is attachable only when the actor SEES it and may UPDATE it
| (TaskAbilityResolver::canCreateSubtask). Both refusals share one 422
| message on parent_task_id so the response never reveals whether the
| parent exists. PATCH re-judges the parent only when it actually changes.
*/

const PARENT_NOT_AVAILABLE_MESSAGE = 'The selected parent task is not available.';

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

if (! function_exists('subtaskPayload')) {
    /**
     * @return array<string, mixed>
     */
    function subtaskPayload(Task $parent, string $title): array
    {
        return [
            'title' => $title,
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            'parent_task_id' => $parent->id,
        ];
    }
}

beforeEach(function () {
    foreach (['viewAny', 'view', 'create', 'update', 'viewAll', 'manageAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }
});

// ---------------------------------------------------------------------------
// AC-008..AC-011 — POST /api/tasks with parent_task_id
// ---------------------------------------------------------------------------

it('AC-008: 422 on parent_task_id when the actor cannot see the parent, nothing created', function () {
    $actor = taskActorWith(['create'], withViewAll: false);
    $parent = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', subtaskPayload($parent, 'Figlio di un invisibile'))
        ->assertStatus(422)
        ->assertJsonPath('errors.parent_task_id.0', PARENT_NOT_AVAILABLE_MESSAGE);

    $this->assertDatabaseMissing('tasks', ['title' => 'Figlio di un invisibile']);
});

it('AC-009: 422 with the same message when the actor sees the parent (viewAll) but holds no role on it', function () {
    $actor = taskActorWith(['create']);
    $parent = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', subtaskPayload($parent, 'Figlio senza ruolo'))
        ->assertStatus(422)
        ->assertJsonPath('errors.parent_task_id.0', PARENT_NOT_AVAILABLE_MESSAGE);

    $this->assertDatabaseMissing('tasks', ['title' => 'Figlio senza ruolo']);
});

it('AC-010: the watcher of the parent gets 422', function () {
    $actor = taskActorWith(['create'], withViewAll: false);
    $parent = Task::factory()->create();
    $parent->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', subtaskPayload($parent, 'Figlio di osservatore'))
        ->assertStatus(422)
        ->assertJsonPath('errors.parent_task_id.0', PARENT_NOT_AVAILABLE_MESSAGE);
});

it('AC-010: assignee, creator and requester of the parent create the sub-task (201)', function (string $role) {
    $actor = taskActorWith(['create'], withViewAll: false);
    $parent = match ($role) {
        'creator' => Task::factory()->forCreator($actor)->create(),
        'requester' => Task::factory()->create(['requester_id' => $actor->id]),
        'assignee' => tap(Task::factory()->create(), fn (Task $task) => $task->assignees()->attach($actor->id)),
    };
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', subtaskPayload($parent, "Figlio di {$role}"))->assertCreated();

    $this->assertDatabaseHas('tasks', ['title' => "Figlio di {$role}", 'parent_task_id' => $parent->id]);
})->with(['assignee', 'creator', 'requester']);

it('AC-011: a manager (manageAll + viewAll) with no role on the parent creates the sub-task (201)', function () {
    $actor = taskActorWith(['create', 'manageAll']);
    $parent = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', subtaskPayload($parent, 'Figlio del gestore'))->assertCreated();

    $this->assertDatabaseHas('tasks', ['title' => 'Figlio del gestore', 'parent_task_id' => $parent->id]);
});

// ---------------------------------------------------------------------------
// AC-012/AC-013 — PATCH /api/tasks/{task} with parent_task_id
// ---------------------------------------------------------------------------

it('AC-012: the creator of T cannot move it under a parent they cannot see: 422, parent unchanged', function () {
    $actor = taskActorWith(['view', 'update'], withViewAll: false);
    $task = Task::factory()->forCreator($actor)->create();
    $foreignParent = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['parent_task_id' => $foreignParent->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.parent_task_id.0', PARENT_NOT_AVAILABLE_MESSAGE);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'parent_task_id' => null]);
});

it('AC-013: an unchanged parent is not re-judged, and detaching (null) needs no role on the parent', function () {
    $actor = taskActorWith(['view', 'update'], withViewAll: false);
    $parent = Task::factory()->create();
    $task = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['parent_task_id' => $parent->id, 'description' => 'Aggiornata'])
        ->assertOk();
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'parent_task_id' => $parent->id, 'description' => 'Aggiornata']);

    $this->patchJson("/api/tasks/{$task->id}", ['parent_task_id' => null])->assertOk();
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'parent_task_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-014 — permissions.actions.create_subtask
// ---------------------------------------------------------------------------

it('AC-014: create_subtask is false for the watcher, true for the assignee, false on a blocked parent even for the creator', function () {
    $watcher = taskActorWith(['view', 'create'], withViewAll: false);
    $assignee = taskActorWith(['view', 'create'], withViewAll: false);
    $creator = taskActorWith(['view', 'create'], withViewAll: false);
    $task = Task::factory()->forCreator($creator)->create();
    $task->watchers()->attach($watcher->id);
    $task->assignees()->attach($assignee->id);
    $blocked = Task::factory()->forCreator($creator)->create(['is_blocked' => true]);

    Sanctum::actingAs($watcher);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.create_subtask', false);

    Sanctum::actingAs($assignee);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.create_subtask', true);

    Sanctum::actingAs($creator);
    $this->getJson("/api/tasks/{$blocked->id}")->assertOk()->assertJsonPath('permissions.actions.create_subtask', false);
});
