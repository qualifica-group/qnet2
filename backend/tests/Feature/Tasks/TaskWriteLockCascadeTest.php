<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Write-lock cascade (spec 0123, D-9, AC-030..AC-034)
|--------------------------------------------------------------------------
|
| A Task whose parent, or any ancestor further up, is TaskWriteLock::isLocked()
| is STRUCTURALLY frozen exactly like a directly frozen one (422 per
| structural key on PATCH, 409 on DELETE) — but its OWN operative actions
| (status change, note, segnatempo, complete) stay untouched: the cascade is
| purely structural (decision utente). The mirror rule guards the EDGE
| itself: creating a Task under a frozen parent_task_id, or PATCHing one onto
| it, is 422 on `parent_task_id`.
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
// AC-030 — a blocked parent structurally freezes the child, operative stays
// ---------------------------------------------------------------------------

it('AC-030: PATCHing a structural key (title) on a child of a blocked parent is 422 on title, child invariato', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$child->id}", ['title' => 'Nuovo titolo'])
        ->assertStatus(422)->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', ['id' => $child->id, 'title' => 'Originale']);
});

it('AC-030: PATCHing only task_status_id on a child of a blocked parent toward pending is 200', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    $pending = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$child->id}", ['task_status_id' => $pending->id])
        ->assertOk()->assertJsonPath('data.task_status_id', $pending->id);

    $this->assertDatabaseHas('tasks', ['id' => $child->id, 'task_status_id' => $pending->id]);
});

// ---------------------------------------------------------------------------
// AC-031 — the whole chain locks, not just the direct parent
// ---------------------------------------------------------------------------

it('AC-031: a grandparent in closed_positive freezes a grandchild two levels down', function () {
    $actor = taskActorWith(['view', 'update']);
    $closedPositive = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $grandparent = Task::factory()->forCreator($actor)->inStatus($closedPositive)->create();
    $parent = Task::factory()->forCreator($actor)->childOf($grandparent)->create();
    $grandchild = Task::factory()->forCreator($actor)->childOf($parent)->create(['description' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$grandchild->id}", ['description' => 'Nuova descrizione'])
        ->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseHas('tasks', ['id' => $grandchild->id, 'description' => 'Originale']);
});

// ---------------------------------------------------------------------------
// AC-032 — DELETE gets no operative exception either, cascaded
// ---------------------------------------------------------------------------

it('AC-032: DELETE of a child whose parent is in_validation is 409, child still present', function () {
    $actor = taskActorWith(['view', 'delete']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$child->id}")->assertStatus(409);

    $this->assertDatabaseHas('tasks', ['id' => $child->id]);
});

// ---------------------------------------------------------------------------
// AC-033 — the edge itself: create/PATCH onto a frozen chain
// ---------------------------------------------------------------------------

it('AC-033: POST /api/tasks with a blocked parent_task_id is 422 on parent_task_id, nothing created', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $blockedParent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Sotto un padre congelato',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'end_date' => '2026-12-31',
        'parent_task_id' => $blockedParent->id,
    ])->assertStatus(422)->assertJsonValidationErrors('parent_task_id');

    $this->assertDatabaseMissing('tasks', ['title' => 'Sotto un padre congelato']);
});

it('AC-033: PATCHing parent_task_id of a free Task onto a blocked one is 422 on parent_task_id, unchanged', function () {
    $actor = taskActorWith(['view', 'update']);
    $blockedParent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $free = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$free->id}", ['parent_task_id' => $blockedParent->id])
        ->assertStatus(422)->assertJsonValidationErrors('parent_task_id');

    $this->assertDatabaseHas('tasks', ['id' => $free->id, 'parent_task_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-034 — operative actions on the child are NOT affected by the cascade
// ---------------------------------------------------------------------------

it('AC-034: /complete on a child of a blocked parent still succeeds', function () {
    $actor = taskActorWith(['view', 'update', 'complete']);
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$child->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()->assertJsonPath('data.completion_percentage', 100);
});

it('AC-034: a note on a child of a blocked parent still succeeds', function () {
    $actor = taskActorWith(['view', 'update']);
    Permission::findOrCreate('notes.create');
    $actor->givePermissionTo('notes.create');
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $child->id,
        'body' => 'Commento sul figlio congelato a monte',
    ])->assertCreated();
});

it('AC-034: a segnatempo on a child of a blocked parent still succeeds', function () {
    $actor = taskActorWith(['view', 'update', 'complete']);
    Permission::findOrCreate('time-entries.create');
    $actor->givePermissionTo('time-entries.create');
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$child->id}/time-entries", validTimeEntryPayload())
        ->assertCreated();
});
