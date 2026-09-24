<?php

use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskStatusGroup;
use App\Models\Attachment;
use App\Models\Note;
use App\Models\Task;
use App\Models\TaskRecurrence;
use App\Models\TaskStatus;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The `recurrence` contract on POST/PATCH /api/tasks (spec 0120,
| AC-022..AC-029, AC-031)
|--------------------------------------------------------------------------
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

if (! function_exists('taskPayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Prima attivita',
            'requester_id' => User::factory()->create()->id,
            'assignee_ids' => [User::factory()->create()->id],
            'end_date' => '2026-12-31',
            ...$overrides,
        ];
    }
}

if (! function_exists('recurrencePayload')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function recurrencePayload(array $overrides = []): array
    {
        return [
            'frequency' => 'weekly',
            'interval' => 1,
            'weekdays' => [1],
            'ends' => 'never',
            ...$overrides,
        ];
    }
}

if (! function_exists('openTaskStatus0120')) {
    function openTaskStatus0120(): TaskStatus
    {
        return TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    }
}

// ---------------------------------------------------------------------------
// D-3/D-4 — POST creates the series and links the capostipite as occurrence 1
// ---------------------------------------------------------------------------

it('D-3/D-4: POST with a recurrence object creates the series, links the Task, and the response carries it', function () {
    $actor = taskActorWith(['create', 'view']);
    Sanctum::actingAs($actor);

    $dailyPayload = recurrencePayload(['frequency' => 'daily']);
    unset($dailyPayload['weekdays']);

    $response = $this->postJson('/api/tasks', taskPayload(['recurrence' => $dailyPayload]))
        ->assertCreated();

    $taskId = $response->json('data.id');
    $task = Task::query()->findOrFail($taskId);

    expect($response->json('data.recurrence.frequency'))->toBe('daily')
        ->and($task->task_recurrence_id)->not->toBeNull()
        ->and(TaskRecurrence::query()->find($task->task_recurrence_id))->not->toBeNull();
});

// Spec 0155, D-1: month_mode is optional — a monthly rule written the pre-0155
// way (no month_mode) is still accepted and means "fixed day".
it('D-1 (spec 0155): a monthly recurrence without month_mode is accepted as a fixed day', function () {
    Sanctum::actingAs(taskActorWith(['create', 'view']));

    $monthlyPayload = recurrencePayload(['frequency' => 'monthly', 'month_day' => 15]);
    unset($monthlyPayload['weekdays']);

    $response = $this->postJson('/api/tasks', taskPayload(['recurrence' => $monthlyPayload]))->assertCreated();

    expect($response->json('data.recurrence.month_day'))->toBe(15)
        ->and($response->json('data.recurrence.month_mode'))->toBeNull();
});

it('D-1 (spec 0155): month_day is refused with an ordinal month_mode and required without it', function () {
    Sanctum::actingAs(taskActorWith(['create', 'view']));

    $ordinal = recurrencePayload(['frequency' => 'monthly', 'month_mode' => 'ordinal', 'ordinal' => 2, 'ordinal_weekday' => 2, 'month_day' => 15]);
    unset($ordinal['weekdays']);
    $missingDay = recurrencePayload(['frequency' => 'monthly']);
    unset($missingDay['weekdays']);

    $this->postJson('/api/tasks', taskPayload(['recurrence' => $ordinal]))
        ->assertUnprocessable()->assertJsonValidationErrors('recurrence.month_day');
    $this->postJson('/api/tasks', taskPayload(['recurrence' => $missingDay]))
        ->assertUnprocessable()->assertJsonValidationErrors('recurrence.month_day');
});

// ---------------------------------------------------------------------------
// AC-022/AC-025 — D-10/D-11 regeneration on rule change
// ---------------------------------------------------------------------------

it('AC-022: changing the rule removes and recalculates only the future VIRGIN occurrences; past ones and a future one with a note survive', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openTaskStatus0120();

    $recurrence = TaskRecurrence::factory()->create(['frequency' => TaskRecurrenceFrequency::Daily->value]);
    $capostipite = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $pastOccurrence = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->subDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $futureVirgin = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $futureWithNote = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(5)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    Note::factory()->create(['notable_type' => 'task', 'notable_id' => $futureWithNote->id]);

    // REQUIREMENT CHANGED (spec 0155, D-1): a monthly rule now also needs
    // `month_mode` (fixed here, the pre-existing behaviour).
    $monthlyPayload = recurrencePayload(['frequency' => 'monthly', 'interval' => 1, 'month_mode' => 'fixed', 'month_day' => 15]);
    unset($monthlyPayload['weekdays']);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => $monthlyPayload])
        ->assertOk();

    expect(Task::query()->find($futureVirgin->id))->toBeNull()
        ->and(Task::query()->find($pastOccurrence->id))->not->toBeNull()
        ->and(Task::query()->find($futureWithNote->id))->not->toBeNull()
        ->and($recurrence->fresh()->frequency)->toBe(TaskRecurrenceFrequency::Monthly)
        ->and($recurrence->fresh()->generated_until)->toBeNull();
});

it('AC-025: a future blocked, or in-validation, or document-carrying occurrence is not virgin and survives regeneration', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $openStatus = openTaskStatus0120();
    $validationStatus = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();

    $recurrence = TaskRecurrence::factory()->create();
    $capostipite = Task::factory()->forCreator($creator)->inStatus($openStatus)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);

    $blocked = Task::factory()->inStatus($openStatus)
        ->create(['end_date' => now()->addDays(3)->toDateString(), 'task_recurrence_id' => $recurrence->id, 'is_blocked' => true]);
    $inValidation = Task::factory()->inStatus($validationStatus)
        ->create(['end_date' => now()->addDays(4)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $withDocument = Task::factory()->inStatus($openStatus)
        ->create(['end_date' => now()->addDays(6)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    Attachment::factory()->for($withDocument, 'attachable')->create(['collection' => 'documents']);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => recurrencePayload()])->assertOk();

    expect(Task::query()->find($blocked->id))->not->toBeNull()
        ->and(Task::query()->find($inValidation->id))->not->toBeNull()
        ->and(Task::query()->find($withDocument->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-004 (spec 0155, D-2) — pruning ignores untouched copied sub-tasks
// ---------------------------------------------------------------------------

it('AC-004 (spec 0155): a future occurrence whose only sub-task is an untouched copy is still virgin and gets pruned', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openTaskStatus0120();

    $recurrence = TaskRecurrence::factory()->create();
    $capostipite = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $futureOccurrence = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    Task::factory()->childOf($futureOccurrence)->inStatus($status)->create();

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => recurrencePayload()])->assertOk();

    expect(Task::query()->find($futureOccurrence->id))->toBeNull();
});

it('AC-004 (spec 0155): a future occurrence whose copied sub-task carries a note is NOT virgin and survives', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openTaskStatus0120();

    $recurrence = TaskRecurrence::factory()->create();
    $capostipite = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $futureOccurrence = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $copiedSubtask = Task::factory()->childOf($futureOccurrence)->inStatus($status)->create();
    Note::factory()->create(['notable_type' => 'task', 'notable_id' => $copiedSubtask->id]);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => recurrencePayload()])->assertOk();

    expect(Task::query()->find($futureOccurrence->id))->not->toBeNull();
});

it('AC-004 (spec 0155): a future occurrence whose copied sub-task was later completed is NOT virgin and survives', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $openStatus = openTaskStatus0120();
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $recurrence = TaskRecurrence::factory()->create();
    $capostipite = Task::factory()->forCreator($creator)->inStatus($openStatus)
        ->create(['end_date' => now()->subDays(30)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $futureOccurrence = Task::factory()->inStatus($openStatus)
        ->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $copiedSubtask = Task::factory()->childOf($futureOccurrence)->inStatus($openStatus)->create();
    $copiedSubtask->update(['task_status_id' => $closedStatus->id, 'completion_date' => now()->toDateString()]);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => recurrencePayload()])->assertOk();

    expect(Task::query()->find($futureOccurrence->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-023/AC-024 — the three-way PATCH semantic
// ---------------------------------------------------------------------------

it('AC-023: PATCH recurrence: null cancels the series; the generated occurrences survive, unlinked', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openTaskStatus0120();

    $recurrence = TaskRecurrence::factory()->create();
    $capostipite = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $occurrence = Task::factory()->inStatus($status)
        ->create(['end_date' => now()->subDays(5)->toDateString(), 'task_recurrence_id' => $recurrence->id]);

    $this->patchJson("/api/tasks/{$capostipite->id}", ['recurrence' => null])->assertOk();

    expect(TaskRecurrence::query()->find($recurrence->id))->toBeNull()
        ->and($capostipite->fresh()->task_recurrence_id)->toBeNull()
        ->and($occurrence->fresh()->task_recurrence_id)->toBeNull();
});

it('AC-024: PATCH without the recurrence key leaves the series untouched', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $status = openTaskStatus0120();

    $recurrence = TaskRecurrence::factory()->create();
    $task = Task::factory()->forCreator($creator)->inStatus($status)
        ->create(['end_date' => now()->subDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id, 'title' => 'Prima']);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Dopo'])->assertOk();

    expect($task->fresh()->task_recurrence_id)->toBe($recurrence->id)
        ->and(TaskRecurrence::query()->find($recurrence->id))->not->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-026/AC-027 — validation
// ---------------------------------------------------------------------------

it('AC-026: weekly without weekdays is 422 on recurrence.weekdays', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['recurrence' => recurrencePayload(['frequency' => 'weekly', 'weekdays' => null])]))
        ->assertStatus(422)->assertJsonValidationErrors('recurrence.weekdays');
});

it('AC-026: daily WITH weekdays present is 422 on recurrence.weekdays (prohibited field)', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', taskPayload(['recurrence' => recurrencePayload(['frequency' => 'daily', 'weekdays' => [1]])]))
        ->assertStatus(422)->assertJsonValidationErrors('recurrence.weekdays');
});

it('AC-027: recurrence submitted with end_date absent is 422 on recurrence, naming the missing deadline', function () {
    $actor = taskActorWith(['create']);
    Sanctum::actingAs($actor);

    $payload = taskPayload(['recurrence' => recurrencePayload()]);
    unset($payload['end_date']);

    $this->postJson('/api/tasks', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('recurrence');
});

// ---------------------------------------------------------------------------
// AC-028 — D-12, the protected-field 403
// ---------------------------------------------------------------------------

it('AC-028: an assignee who may edit the Task but is not creator/requester/manager gets 403 sending recurrence; the same PATCH without it succeeds', function () {
    $creator = User::factory()->create();
    $assignee = taskActorWith(['update', 'view']);
    $status = openTaskStatus0120();
    $task = Task::factory()->forCreator($creator)->inStatus($status)->create(['end_date' => now()->addDays(10)->toDateString()]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->patchJson("/api/tasks/{$task->id}", ['recurrence' => recurrencePayload()])
        ->assertStatus(403);

    $this->patchJson("/api/tasks/{$task->id}", ['description' => 'Aggiornamento libero'])
        ->assertOk();
});

// ---------------------------------------------------------------------------
// AC-029 — D-13, the structural write lock
// ---------------------------------------------------------------------------

it('AC-029: PATCH sending recurrence on a blocked task is 422 on recurrence, and the task does not change', function () {
    $creator = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($creator);
    $task = Task::factory()->forCreator($creator)->create(['end_date' => now()->addDays(10)->toDateString(), 'is_blocked' => true]);

    $this->patchJson("/api/tasks/{$task->id}", ['recurrence' => recurrencePayload()])
        ->assertStatus(422)->assertJsonValidationErrors('recurrence');

    expect($task->fresh()->task_recurrence_id)->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-031 — GET exposes data.recurrence, permissions.actions stays at 13
// ---------------------------------------------------------------------------

it('AC-031: GET exposes data.recurrence as a full object when set, null otherwise, and permissions.actions keeps 15 keys', function () {
    $actor = taskActorWith(['create', 'update', 'view']);
    Sanctum::actingAs($actor);
    $recurrence = TaskRecurrence::factory()->create([
        'frequency' => TaskRecurrenceFrequency::Weekly->value,
        'interval' => 2,
        'weekdays' => [1, 3],
        'ends' => TaskRecurrenceEnd::Never->value,
    ]);
    $task = Task::factory()->forCreator($actor)->create(['end_date' => now()->addDays(10)->toDateString(), 'task_recurrence_id' => $recurrence->id]);
    $plainTask = Task::factory()->forCreator($actor)->create();

    $response = $this->getJson("/api/tasks/{$task->id}")->assertOk();

    // REQUIREMENT CHANGED (spec 0155, D-1): the contract grows five fields
    // (month_mode/ordinal/ordinal_weekday/year_month/workdays_only), null/
    // false on a plain weekly series like this one.
    expect($response->json('data.recurrence'))->toBe([
        'id' => $recurrence->id,
        'frequency' => 'weekly',
        'interval' => 2,
        'weekdays' => [1, 3],
        'month_day' => null,
        'month_mode' => null,
        'ordinal' => null,
        'ordinal_weekday' => null,
        'year_month' => null,
        'workdays_only' => false,
        'ends' => 'never',
        'ends_on' => null,
        'occurrence_count' => null,
    ])
        // REQUIREMENT CHANGED (spec 0126, D-4): actions() grows to 16 keys.
        ->and($response->json('permissions.actions'))->toHaveCount(16);

    $this->getJson("/api/tasks/{$plainTask->id}")->assertOk()->assertJsonPath('data.recurrence', null);
});

it('D-12: recurrence is exposed as a protected field, readonly for a bare assignee', function () {
    $actor = taskActorWith(['view', 'update']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $fields = collect($this->getJson("/api/tasks/{$task->id}")->assertOk()->json('permissions.fields'));

    expect($fields['recurrence']['editable'])->toBeFalse()
        ->and(TaskAbilityResolver::PROTECTED_FIELDS)->toContain('recurrence');
});
