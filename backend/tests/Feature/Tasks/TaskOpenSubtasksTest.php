<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| A parent Task is not completable with open DIRECT sub-tasks (spec 0123,
| D-6, AC-018..AC-022)
|--------------------------------------------------------------------------
|
| `in_validation` counts as open. The count IGNORES TaskVisibilityScope
| (AC-021), the same rule App\Services\TaskService::delete() already applies
| to child counting. Nipoti (level > 1) never count — out of scope.
*/

if (! function_exists('taskOpenSubtasksActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskOpenSubtasksActorWith(array $abilities, bool $withViewAll = true): User
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

if (! function_exists('protectedTaskStatus')) {
    function protectedTaskStatus(TaskStatusSystemKey $key): TaskStatus
    {
        return TaskStatus::query()->where('system_key', $key->value)->firstOrFail();
    }
}

// ---------------------------------------------------------------------------
// AC-018/AC-019 — /complete refuses a parent with an open direct child
// ---------------------------------------------------------------------------

it('AC-018: a parent with a direct open child is 422 on /complete, status invariato, zero time_entries', function () {
    $actor = taskOpenSubtasksActorWith(['complete']);
    $parent = Task::factory()->create();
    $parent->assignees()->attach($actor->id);
    Task::factory()->create(['parent_task_id' => $parent->id]); // default phase: open
    $originalStatusId = $parent->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)
        ->assertJsonPath('message', 'This task has open sub-tasks: close them before completing it.');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'task_status_id' => $originalStatusId]);
    expect(TimeEntry::where('task_id', $parent->id)->count())->toBe(0);
});

it('AC-019: an in_validation direct child blocks /complete; closing every child unblocks it', function () {
    $actor = taskOpenSubtasksActorWith(['complete']);
    $parent = Task::factory()->create();
    $parent->assignees()->attach($actor->id);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $child = Task::factory()->inStatus($inValidation)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422);

    $child->task_status_id = protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id;
    $child->save();

    $this->postJson("/api/tasks/{$parent->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk();
});

// ---------------------------------------------------------------------------
// AC-020 — /approve refuses a parent with a reopened direct child
// ---------------------------------------------------------------------------

it('AC-020: a reopened direct child blocks /approve, status invariato', function () {
    $actor = taskOpenSubtasksActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    Task::factory()->create(['parent_task_id' => $parent->id]); // reopened: default phase open
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/approve")->assertStatus(422);

    $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'task_status_id' => $inValidation->id]);
});

// ---------------------------------------------------------------------------
// AC-021 — the count ignores TaskVisibilityScope
// ---------------------------------------------------------------------------

it('AC-021: an open child invisible to the actor still blocks /complete', function () {
    $actor = taskOpenSubtasksActorWith(['complete'], withViewAll: false);
    $parent = Task::factory()->create();
    $parent->assignees()->attach($actor->id);
    Task::factory()->create(['parent_task_id' => $parent->id]); // unrelated creator, no membership: invisible to $actor
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$parent->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422);

    expect(TimeEntry::where('task_id', $parent->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-022 — GET exposes open_subtasks_count and the permissions AND it in
// ---------------------------------------------------------------------------

it('AC-022: GET exposes open_subtasks_count, and it disables complete/complete_to_validation while > 0', function () {
    $actor = taskOpenSubtasksActorWith(['view', 'complete']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    Task::factory()->create(['parent_task_id' => $task->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.open_subtasks_count', 1)
        ->assertJsonPath('permissions.actions.complete', false)
        ->assertJsonPath('permissions.actions.complete_to_validation', false);
});

it('AC-022: open_subtasks_count also disables approve while > 0', function () {
    $actor = taskOpenSubtasksActorWith(['view', 'validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    Task::factory()->create(['parent_task_id' => $task->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.open_subtasks_count', 1)
        ->assertJsonPath('permissions.actions.approve', false);
});
