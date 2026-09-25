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
| Kanban partial PATCH (spec 0157, D-3/D-4)
|--------------------------------------------------------------------------
|
| The Kanban view moves a card with a plain PATCH /api/tasks/{id} carrying
| ONLY `task_status_id` (status columns) or ONLY `end_date` (due-date
| columns), never a full payload — no new endpoint, the EXISTING guards
| (TaskManualStatusGuard, TaskActionOnlyStatusGuard, TaskWriteLock) already
| enforce the D-3 rules unmodified: a drag between open statuses succeeds, a
| drag INTO a status reserved for the Complete action is refused, and a
| structural PATCH (end_date included) on a frozen/closed Task is refused.
*/

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom (see TaskTableTest.php).
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

it('D-3: PATCH with only task_status_id moves the card between two open statuses', function () {
    $actor = taskActorWith(['update', 'view']);
    $target = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->create(['title' => 'Invariato']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.title', 'Invariato');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $target->id, 'title' => 'Invariato']);
});

it('D-3: PATCH with only task_status_id into a status reserved to Complete is refused by the existing guard', function () {
    $actor = taskActorWith(['update', 'view']);
    $reserved = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->create();
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $reserved->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.task_status_id.0', 'This status can only be reached through the Complete action.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
});

it('D-4: PATCH with only end_date moves the card on an open task', function () {
    $actor = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($actor)->create(['end_date' => '2026-01-10']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['end_date' => '2026-02-15'])
        ->assertOk()
        ->assertJsonPath('data.end_date', '2026-02-15');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'end_date' => '2026-02-15']);
});

it('D-4: PATCH with only end_date on a closed task is refused by the existing structural write lock', function () {
    $actor = taskActorWith(['update', 'view']);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create(['end_date' => '2026-01-10']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['end_date' => '2026-02-15'])
        ->assertStatus(422)
        ->assertJsonPath('errors.end_date.0', 'This task is frozen: only its status and closure feedback can be changed.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'end_date' => '2026-01-10']);
});
