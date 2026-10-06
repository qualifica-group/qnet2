<?php

use App\Enums\TaskStatusGroup;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The detail's field permissions match what the PATCH accepts (spec 0195)
|--------------------------------------------------------------------------
|
| The task detail edits in place, one field at a time, offering an editor
| exactly where `permissions.fields.*.editable` says so. These cases pin the
| two gates the ceiling now folds in — TaskPolicy::update() and the
| TaskWriteLock freeze — next to the per-role matrix, and that each refused
| PATCH keeps its own failure (403 / frozen message) rather than a generic
| field-level 422.
*/

/**
 * @return array<int, string>
 */
function editableTaskFields(User $actor, Task $task): array
{
    Sanctum::actingAs($actor);

    return collect(test()->getJson("/api/tasks/{$task->id}")->assertOk()->json('permissions.fields'))
        ->filter(fn (array $permission): bool => $permission['editable'])
        ->keys()
        ->all();
}

it('a bare watcher holding tasks.update sees no editable field, no change_status, and the PATCH stays a 403', function () {
    $actor = taskCompletionActorWith(['view', 'update']);
    $task = Task::factory()->create(['description' => '<p>originale</p>']);
    $task->watchers()->attach($actor->id);

    expect(editableTaskFields($actor, $task))->toBe([]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertJsonPath('permissions.actions.change_status', false)
        ->assertJsonPath('permissions.actions.close_via_status', false);

    $this->patchJson("/api/tasks/{$task->id}", ['description' => '<p>nuova</p>'])->assertForbidden();
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'description' => '<p>originale</p>']);
});

it('an assignee keeps the free fields editable on an open task', function () {
    $actor = taskCompletionActorWith(['view', 'update']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);

    expect(editableTaskFields($actor, $task))
        ->toEqualCanonicalizing(['task_status_id', 'description', 'start_time', 'end_time', 'closure_feedback', 'is_private']);
});

it('a closed task leaves only the operative keys editable for its creator', function () {
    $actor = taskCompletionActorWith(['view', 'update']);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create(['title' => 'Originale']);

    expect(editableTaskFields($actor, $task))->toEqualCanonicalizing(TaskWriteLock::OPERATIVE_KEYS);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo'])
        ->assertStatus(422)
        ->assertJsonPath('errors.title.0', TaskWriteLock::STRUCTURAL_WRITE_MESSAGE);
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Originale']);
});

it('a sub-task of a blocked parent leaves only the operative keys editable', function () {
    $actor = taskCompletionActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();

    expect(editableTaskFields($actor, $child))->toEqualCanonicalizing(TaskWriteLock::OPERATIVE_KEYS);
});

it('the super-admin keeps every field editable on a closed task (D-8 carve-out)', function () {
    Role::findOrCreate('super-admin');
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    $task = Task::factory()->inStatus($closed)->create();

    expect(editableTaskFields($actor, $task))->toContain('title', 'end_date', 'assignee_ids');
});

it('the role field matrix reaches the instance detail: hidden and readonly fields, refused on PATCH', function () {
    $actor = taskCompletionActorWith(['view', 'update']);
    $role = Role::create(['name' => 'task-detail-matrix']);
    $role->fieldPermissions()->create(['resource' => 'tasks', 'field' => 'description', 'visible' => false, 'editable' => false, 'required' => false]);
    $role->fieldPermissions()->create(['resource' => 'tasks', 'field' => 'start_time', 'visible' => true, 'editable' => false, 'required' => false]);
    $actor->assignRole($role);
    $task = Task::factory()->forCreator($actor)->create(['start_time' => '09:00']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.description.visible', false)
        ->assertJsonPath('permissions.fields.start_time.readonly', true)
        ->assertJsonPath('permissions.fields.title.editable', true);

    $this->patchJson("/api/tasks/{$task->id}", ['start_time' => '10:00'])
        ->assertStatus(422)
        ->assertJsonPath('errors.start_time.0', 'field not editable');
});
