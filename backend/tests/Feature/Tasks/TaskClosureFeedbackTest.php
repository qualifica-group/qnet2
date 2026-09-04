<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Closure feedback guard (spec 0101, D-7, AC-030..AC-035)
|--------------------------------------------------------------------------
|
| The rule looks at the RESULTING task, not at the submitted payload: flag
| and feedback are read from the persisted record whenever the request does
| not carry them. That is why AC-035 (a PATCH sending ONLY task_status_id)
| is the load-bearing case — it is exactly the one a FormRequest cannot
| decide, which is why the guard lives in TaskClosureFeedbackGuard, inside
| the Service transaction.
|
| Every 422 here is paired with an assertion that the status did NOT move:
| a guard that rejects the response but commits the write would satisfy the
| status code alone.
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
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
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

/**
 * The two closing system statuses (D-5). A CUSTOM status is deliberately
 * absent: belonging to no phase, it is never closing (AC-034).
 */
dataset('closingTaskStatuses', [
    'closed_positive' => [TaskStatusSystemKey::ClosedPositive],
    'closed_negative' => [TaskStatusSystemKey::ClosedNegative],
]);

if (! function_exists('closingTaskStatus')) {
    function closingTaskStatus(TaskStatusSystemKey $key): TaskStatus
    {
        return TaskStatus::query()->where('system_key', $key->value)->firstOrFail();
    }
}

// ---------------------------------------------------------------------------
// AC-030 / AC-031 — flag on, closing status, no usable feedback -> 422
// ---------------------------------------------------------------------------

it('AC-030: 422 on closure_feedback when a flagged task is closed without feedback, and the status does not move', function (TaskStatusSystemKey $key) {
    $actor = taskActorWith(['view', 'update']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()->inStatus($open)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => closingTaskStatus($key)->id])
        ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
})->with('closingTaskStatuses');

it('AC-031: 422 when the feedback is whitespace only: the check trims before deciding', function (TaskStatusSystemKey $key) {
    $actor = taskActorWith(['view', 'update']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()->inStatus($open)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'task_status_id' => closingTaskStatus($key)->id,
        'closure_feedback' => "   \n\t  ",
    ])->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
})->with('closingTaskStatuses');

it('AC-030: the same rule applies on CREATE, not only on update', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Nasce gia chiusa',
        'task_status_id' => closingTaskStatus(TaskStatusSystemKey::ClosedPositive)->id,
        'requires_closure_feedback' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseMissing('tasks', ['title' => 'Nasce gia chiusa']);
});

// ---------------------------------------------------------------------------
// AC-032 / AC-033 / AC-034 — the three ways through the guard
// ---------------------------------------------------------------------------

it('AC-032: 200 and the status moves when the feedback is filled in', function (TaskStatusSystemKey $key) {
    $actor = taskActorWith(['view', 'update']);
    $closing = closingTaskStatus($key);
    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()
        ->inStatus(TaskStatus::factory()->completion(0)->create())->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'task_status_id' => $closing->id,
        'closure_feedback' => 'Consegnato al cliente il 4 settembre.',
    ])->assertOk()->assertJsonPath('data.task_status_id', $closing->id);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_status_id' => $closing->id,
        'closure_feedback' => 'Consegnato al cliente il 4 settembre.',
    ]);
})->with('closingTaskStatuses');

it('AC-033: 200 without feedback when requires_closure_feedback is false', function () {
    $actor = taskActorWith(['view', 'update']);
    $closing = closingTaskStatus(TaskStatusSystemKey::ClosedNegative);
    $task = Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(0)->create())->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closing->id])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $closing->id]);
});

it('AC-034: 200 without feedback when the target status is CUSTOM (system_key NULL), the accepted consequence of D-5', function () {
    $actor = taskActorWith(['view', 'update']);
    // A custom status at 100 per cent: the percentage is NOT what makes a
    // status closing — only the system_key is (D-5).
    $custom = TaskStatus::factory()->completion(100)->create(['name' => 'Archiviato']);
    expect($custom->system_key)->toBeNull();

    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()
        ->inStatus(TaskStatus::factory()->completion(0)->create())->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $custom->id])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $custom->id]);
});

// ---------------------------------------------------------------------------
// AC-035 — the rule is evaluated on the RESULTING record, not on the payload
// ---------------------------------------------------------------------------

it('AC-035: a PATCH sending ONLY task_status_id reads the flag from the persisted record -> 422', function () {
    $actor = taskActorWith(['view', 'update']);
    $open = TaskStatus::factory()->completion(0)->create();
    // The flag is persisted and NOT resubmitted: a FormRequest cannot see
    // it, which is the whole point of D-7.
    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()->inStatus($open)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => closingTaskStatus(TaskStatusSystemKey::ClosedPositive)->id])
        ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

it('AC-035: a PATCH sending ONLY task_status_id passes when the feedback is already persisted', function () {
    $actor = taskActorWith(['view', 'update']);
    $closing = closingTaskStatus(TaskStatusSystemKey::ClosedPositive);
    $task = Task::factory()->forCreator($actor)->requiringClosureFeedback()
        ->inStatus(TaskStatus::factory()->completion(0)->create())
        ->create(['closure_feedback' => 'Motivazione gia registrata.']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closing->id])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $closing->id]);
});

it('AC-035: a PATCH raising the flag on an already-closed task with no feedback -> 422', function () {
    $actor = taskActorWith(['view', 'update']);
    $closing = closingTaskStatus(TaskStatusSystemKey::ClosedNegative);
    // The status is persisted and NOT resubmitted: the mirror image of the
    // case above, and equally undecidable from the payload alone.
    $task = Task::factory()->forCreator($actor)->inStatus($closing)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['requires_closure_feedback' => true])
        ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'requires_closure_feedback' => false]);
});
