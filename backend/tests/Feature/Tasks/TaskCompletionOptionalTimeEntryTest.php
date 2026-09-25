<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Optional segnatempo at task completion, per tipologia (spec 0162,
| AC-001..AC-005/AC-007)
|--------------------------------------------------------------------------
|
| `task_types.requires_time_entry` (D-1) defaults true, so every existing
| type keeps demanding a segnatempo (AC-001). A type with the flag false
| makes it OPTIONAL on the Tasks classified under it (D-2): absent -> the
| Task still closes, no time_entries row at all, even with
| `for_all_assignees` (AC-002); present -> logged exactly as before (AC-003).
| A Task with no tipologia at all behaves like a required one (AC-004). Bulk
| complete (spec 0156) is all-or-nothing over the SAME per-task rule
| (App\Services\Tasks\TaskTimeEntryRequirement, AC-005).
*/

if (! function_exists('taskCompletionActorWith')) {
    /**
     * @param  array<int, string>  $taskAbilities
     */
    function taskCompletionActorWith(array $taskAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.viewAll');

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
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

if (! function_exists('validTimeEntryPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validTimeEntryPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-25',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 30,
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// AC-001 — an existing (pre-migration-shaped) type keeps demanding it
// ---------------------------------------------------------------------------

it('AC-001: a plain task type defaults to requires_time_entry = true', function () {
    expect(TaskType::factory()->create()->requires_time_entry)->toBeTrue();
});

it('AC-001: completing a task of a required-time-entry type without time_entry is 422, invariant', function () {
    $actor = taskCompletionActorWith(['complete']);
    $taskType = TaskType::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $taskType->id]);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_entry');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-002 — an optional type: completing without time_entry closes the task
// ---------------------------------------------------------------------------

it('AC-002: completing a task of an optional-time-entry type without time_entry closes it, zero time_entries rows', function () {
    $actor = taskCompletionActorWith(['complete']);
    $taskType = TaskType::factory()->optionalTimeEntry()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $taskType->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id)
        ->assertJsonPath('data.requires_time_entry', false);

    expect(TimeEntry::query()->count())->toBe(0);
});

it('AC-002: the same task closes without time_entry even with for_all_assignees true and several assignees', function () {
    $actor = taskCompletionActorWith(['complete']);
    $taskType = TaskType::factory()->optionalTimeEntry()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $taskType->id]);
    $task->assignees()->attach([$actor->id, User::factory()->create()->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['for_all_assignees' => true])
        ->assertOk();

    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-003 — an optional type: a submitted time_entry is still logged as today
// ---------------------------------------------------------------------------

it('AC-003: an optional-time-entry task with a submitted time_entry creates the row as before', function () {
    $actor = taskCompletionActorWith(['complete']);
    $taskType = TaskType::factory()->optionalTimeEntry()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $taskType->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload(['minutes' => 45])])
        ->assertOk();

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id, 'minutes' => 45]);
});

it('AC-003: for_all_assignees true logs an identical entry per assignee on an optional-time-entry task', function () {
    $actor = taskCompletionActorWith(['complete']);
    $taskType = TaskType::factory()->optionalTimeEntry()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $taskType->id]);
    $second = User::factory()->create();
    $task->assignees()->attach([$actor->id, $second->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'time_entry' => validTimeEntryPayload(['minutes' => 20]),
        'for_all_assignees' => true,
    ])->assertOk();

    $this->assertDatabaseCount('time_entries', 2);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id, 'minutes' => 20]);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $second->id, 'minutes' => 20]);
});

// ---------------------------------------------------------------------------
// AC-004 — a task with no tipologia at all still requires it
// ---------------------------------------------------------------------------

it('AC-004: a task with no task_type_id still requires time_entry', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => null]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_entry');

    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005 — bulk complete: all-or-nothing over the same per-task rule
// ---------------------------------------------------------------------------

it('AC-005: bulk complete without time_entry is 422 incompatible_tasks when one selected task requires it, nothing changes', function () {
    $actor = taskCompletionActorWith(['complete']);
    $optionalType = TaskType::factory()->optionalTimeEntry()->create();
    $optionalTask = Task::factory()->forCreator($actor)->create(['task_type_id' => $optionalType->id]);
    $requiredTask = Task::factory()->forCreator($actor)->create(['task_type_id' => null]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tasks/bulk', [
        'action' => 'complete', 'task_ids' => [$optionalTask->id, $requiredTask->id],
    ])->assertStatus(422);

    expect($response->json('incompatible_tasks.0.id'))->toBe($requiredTask->id)
        ->and($response->json('errors.task_ids'))->not->toBeEmpty();

    $this->assertDatabaseHas('tasks', ['id' => $optionalTask->id, 'task_status_id' => $optionalTask->task_status_id]);
    $this->assertDatabaseHas('tasks', ['id' => $requiredTask->id, 'task_status_id' => $requiredTask->task_status_id]);
    expect(TimeEntry::query()->count())->toBe(0);
});

it('AC-005: bulk complete without time_entry closes every task when all selected tasks are optional', function () {
    $actor = taskCompletionActorWith(['complete']);
    $optionalType = TaskType::factory()->optionalTimeEntry()->create();
    $one = Task::factory()->forCreator($actor)->create(['task_type_id' => $optionalType->id]);
    $two = Task::factory()->forCreator($actor)->create(['task_type_id' => $optionalType->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'task_ids' => [$one->id, $two->id]])
        ->assertOk()
        ->assertJsonPath('data.affected', 2);

    $closed = protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id;
    $this->assertDatabaseHas('tasks', ['id' => $one->id, 'task_status_id' => $closed]);
    $this->assertDatabaseHas('tasks', ['id' => $two->id, 'task_status_id' => $closed]);
    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// data_contract — requires_time_entry on the detail resource and the grid row
// ---------------------------------------------------------------------------

it('exposes requires_time_entry on the task detail and the grid row, from the tipologia', function () {
    $actor = taskCompletionActorWith(['complete', 'view', 'viewAny']);
    $optionalType = TaskType::factory()->optionalTimeEntry()->create();
    $task = Task::factory()->forCreator($actor)->create(['task_type_id' => $optionalType->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.requires_time_entry', false);

    $row = collect(
        $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk()->json('items')
    )->firstWhere('id', $task->id);

    expect($row['requires_time_entry'])->toBeFalse();
});
