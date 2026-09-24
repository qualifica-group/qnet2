<?php

use App\Enums\TaskStatusGroup;
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
| Super-admin structural write-lock bypass (spec 0153, D-8, AC-011)
|--------------------------------------------------------------------------
|
| A super-admin may PATCH the fields of a closed or in-validation Task
| (TaskWriteLock::assertStructuralWriteAllowed()'s frozen-GROUP veto is
| bypassed for this one role, this one write path) — but `is_blocked` stays
| universal, and delete/complete/documents follow the normal rules
| unchanged (D-8 explicitly excludes them). An ordinary manager
| (`tasks.manageAll`, not super-admin) still gets the usual 422/403.
*/

if (! function_exists('superAdminTaskActor')) {
    function superAdminTaskActor(): User
    {
        Role::findOrCreate('super-admin');
        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('managerTaskActor')) {
    function managerTaskActor(): User
    {
        Permission::findOrCreate('tasks.update');
        Permission::findOrCreate('tasks.manageAll');
        Permission::findOrCreate('tasks.delete');
        Permission::findOrCreate('tasks.viewAll');
        $actor = User::factory()->create();
        $actor->givePermissionTo(['tasks.update', 'tasks.manageAll', 'tasks.delete', 'tasks.viewAll']);

        return $actor;
    }
}

it('AC-011 (spec 0153, D-8): a super-admin PATCHes a closed task, title changes, 200', function () {
    $actor = superAdminTaskActor();
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->inStatus($closed)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Nuovo titolo');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Nuovo titolo']);
});

it('AC-011 (spec 0153, D-8): a super-admin PATCHes a task in_validation, title changes, 200', function () {
    $actor = superAdminTaskActor();
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Nuovo titolo');
});

it('AC-011 (spec 0153, D-8): a super-admin still gets 422 PATCHing a BLOCKED task — is_blocked stays universal', function () {
    $actor = superAdminTaskActor();
    $task = Task::factory()->create(['is_blocked' => true, 'title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Originale']);
});

it('AC-011 (spec 0153, D-8): a super-admin may not DELETE a closed task', function () {
    $actor = superAdminTaskActor();
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->inStatus($closed)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(403);

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('AC-011 (spec 0153, D-8): an ordinary manager (tasks.manageAll, not super-admin) still gets 422 on a closed task', function () {
    $actor = managerTaskActor();
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->inStatus($closed)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Originale']);
});
