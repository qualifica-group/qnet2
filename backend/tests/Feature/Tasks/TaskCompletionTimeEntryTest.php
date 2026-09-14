<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Http\Requests\Tasks\CompleteTaskRequest;
use App\Http\Requests\TimeEntries\StoreTaskTimeEntryRequest;
use App\Models\Registry;
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
| `time_entry` on POST /api/tasks/{task}/complete (spec 0123, D-1..D-3,
| AC-001..AC-009)
|--------------------------------------------------------------------------
|
| The segnatempo is now mandatory on BOTH percorsi, created ATOMICALLY with
| the status change inside `TaskCompletionService::complete()`'s own
| transaction, and needs no `time-entries.create` (D-2, completing GRANTS
| the insert). `approve`/`reject`/`uncomplete` never touch `time_entries`.
*/

if (! function_exists('taskCompletionActorWith')) {
    /**
     * An actor holding $taskAbilities on `tasks` (plus `viewAll`) and,
     * optionally, $timeEntryAbilities on `time-entries` — AC-007 needs an
     * actor with the former and deliberately WITHOUT the latter.
     *
     * @param  array<int, string>  $taskAbilities
     * @param  array<int, string>  $timeEntryAbilities
     */
    function taskCompletionActorWith(array $taskAbilities, array $timeEntryAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.viewAll');

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }
        foreach ($timeEntryAbilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
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
            'date' => '2026-09-14',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
        ], $overrides);
    }
}

if (! function_exists('normalizeTimeEntryRuleMap')) {
    /**
     * Keeps only the keys under $prefix (bare, when $prefix is ''), strips
     * the prefix from both keys and any string rule token, and casts rule
     * objects (`Rule::exists`) to string: AC-009 diffs the two FormRequests'
     * rule maps for the SAME shape, and an Exists rule's `where()` closure
     * is never part of its `__toString()`, so casting loses nothing the two
     * sides could differ on.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    function normalizeTimeEntryRuleMap(array $rules, string $prefix): array
    {
        $normalized = [];

        foreach ($rules as $key => $rule) {
            if ($prefix !== '' && ! str_starts_with($key, $prefix.'.')) {
                continue;
            }

            $bareKey = $prefix === '' ? $key : substr($key, strlen($prefix) + 1);
            $normalized[$bareKey] = array_map(
                static function ($item) use ($prefix) {
                    $value = is_object($item) ? (string) $item : $item;

                    return $prefix === '' || ! is_string($value) ? $value : str_replace($prefix.'.', '', $value);
                },
                $rule
            );
        }

        ksort($normalized);

        return $normalized;
    }
}

// ---------------------------------------------------------------------------
// AC-001/AC-002 — a valid time_entry on both percorsi creates the row
// ---------------------------------------------------------------------------

it('AC-001: completing a non-validating task with a valid time_entry closes it and creates one matching time_entries row', function () {
    $actor = taskCompletionActorWith(['complete']);
    $registry = Registry::factory()->create();
    $task = Task::factory()->create(['title' => 'Rapporto cliente', 'registry_id' => $registry->id]);
    $task->assignees()->attach($actor->id);
    $taskType = TaskType::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'time_entry' => validTimeEntryPayload(['date' => '2026-09-14', 'task_type_id' => $taskType->id, 'minutes' => 90]),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id);

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', [
        'task_id' => $task->id,
        'user_id' => $actor->id,
        'date' => '2026-09-14',
        'task_type_id' => $taskType->id,
        'minutes' => 90,
        'title' => 'Rapporto cliente',
        'registry_id' => $registry->id,
    ]);
});

it('AC-002: a flagged task lets an assignee complete into validation with validation_status_id and time_entry, creating the row', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $validationStatus->id);

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id]);
});

// ---------------------------------------------------------------------------
// AC-003/AC-004 — the payload shape, on either percorso
// ---------------------------------------------------------------------------

it('AC-003: /complete without time_entry is 422 on time_entry, the task is untouched, zero time_entries rows', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete")
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_entry');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
    expect(TimeEntry::query()->count())->toBe(0);
});

it('AC-004: :case is 422 on the matching time_entry.* key and no writes', function (array $overrides, string $expectedField) {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload($overrides)])
        ->assertStatus(422)
        ->assertJsonValidationErrors($expectedField);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
    expect(TimeEntry::query()->count())->toBe(0);
})->with([
    'minutes below range (0)' => [['minutes' => 0], 'time_entry.minutes'],
    'minutes above range (1441)' => [['minutes' => 1441], 'time_entry.minutes'],
    'end_time not after start_time' => [['start_time' => '10:00', 'end_time' => '09:00'], 'time_entry.end_time'],
]);

it('AC-004: an inactive task_type_id is 422 on time_entry.task_type_id and no writes', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $inactiveType = TaskType::factory()->create(['is_active' => false]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'time_entry' => validTimeEntryPayload(['task_type_id' => $inactiveType->id]),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('time_entry.task_type_id');

    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-005/AC-006 — atomic rollback: a refusal leaves zero time_entries rows
// ---------------------------------------------------------------------------

it('AC-005: a valid time_entry with empty required feedback is 422 on closure_feedback, zero time_entries rows', function () {
    $actor = taskCompletionActorWith(['complete']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->requiringClosureFeedback()->inStatus($open)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
    expect(TimeEntry::query()->count())->toBe(0);
});

it('AC-006: a blocked task is 409 on /complete even with a valid time_entry, zero time_entries rows', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(409);

    expect(TimeEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-007 — completing grants the insert, D-2
// ---------------------------------------------------------------------------

it('AC-007: an assignee with tasks.complete but no time-entries.create still gets the row from /complete, and 403 from the generic segnatempo POST', function () {
    $actor = taskCompletionActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk();

    $this->assertDatabaseCount('time_entries', 1);

    $this->postJson("/api/tasks/{$task->id}/time-entries", validTimeEntryPayload())
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// AC-008 — approve/reject/uncomplete never touch time_entries
// ---------------------------------------------------------------------------

it('AC-008: approve, reject and uncomplete leave the task\'s time_entries row count unchanged', function () {
    $actor = taskCompletionActorWith(['complete', 'validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();

    $approved = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    Sanctum::actingAs($actor);
    $this->postJson("/api/tasks/{$approved->id}/approve")->assertOk();
    expect(TimeEntry::where('task_id', $approved->id)->count())->toBe(0);

    $rejected = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    $this->postJson("/api/tasks/{$rejected->id}/reject")->assertOk();
    expect(TimeEntry::where('task_id', $rejected->id)->count())->toBe(0);

    $closed = protectedTaskStatus(TaskStatusSystemKey::ClosedPositive);
    $reopened = Task::factory()->forCreator($actor)->inStatus($closed)->create();
    $this->postJson("/api/tasks/{$reopened->id}/uncomplete")->assertOk();
    expect(TimeEntry::where('task_id', $reopened->id)->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-009 — one source of truth for the rules
// ---------------------------------------------------------------------------

it('AC-009: /complete\'s time_entry.* rules and StoreTaskTimeEntryRequest\'s come from the same source', function () {
    $storeRules = normalizeTimeEntryRuleMap((new StoreTaskTimeEntryRequest)->rules(), '');
    $completeRules = normalizeTimeEntryRuleMap((new CompleteTaskRequest)->rules(), 'time_entry');

    expect($completeRules)->toEqual($storeRules);
});
