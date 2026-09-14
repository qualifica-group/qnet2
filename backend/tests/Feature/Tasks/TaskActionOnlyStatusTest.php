<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
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
| States reserved to the domain actions (spec 0123, D-4, AC-010..AC-013)
|--------------------------------------------------------------------------
|
| A PATCH that moves task_status_id to a status whose PHASE is
| `in_validation` or `closed_positive` is refused for every actor, mandate
| or not — those two phases are reachable only through /complete and
| /approve. `closed_negative` carries no such reservation (D-4). The rule
| looks at the RESULTING status and fires only when task_status_id is
| DIRTY (AC-013): a PATCH that leaves it alone is never re-judged.
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
// AC-010 — a manager (mandato pieno) cannot PATCH into in_validation either
// ---------------------------------------------------------------------------

it('AC-010: a manager PATCHing task_status_id toward an in_validation status is 422 on task_status_id, status invariato', function () {
    // `manageAll` grants the "gestore" standing (D-2) only while the actor
    // is NOT also an assignee of this Task — plain enough here since the
    // Task is created with an unrelated creator/assignee set.
    $manager = taskActorWith(['update', 'manageAll']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->inStatus($open)->create();
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($manager);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $inValidation->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

// ---------------------------------------------------------------------------
// AC-011 — the creator cannot PATCH into closed_positive either
// ---------------------------------------------------------------------------

it('AC-011: the creator PATCHing task_status_id toward closed_positive is 422 on task_status_id, status invariato', function () {
    $creator = taskActorWith(['update']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->forCreator($creator)->inStatus($open)->create();
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closedPositive->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

// ---------------------------------------------------------------------------
// AC-011 (super-admin) — D-4 carries NO exemption, not even Gate::before
// ---------------------------------------------------------------------------

it('AC-011: a super-admin PATCHing task_status_id toward closed_positive is STILL 422 on task_status_id, past Gate::before', function () {
    // Role::findOrCreate + assignRole (TaskActionsTest.php:376 pattern):
    // Gate::before grants the super-admin every ability, so the request
    // reaches TaskService::update() the same way any authorized PATCH would
    // — the guard has to refuse it from INSIDE the Service, not rely on a
    // Policy/ability check the super-admin bypasses entirely.
    Role::findOrCreate('super-admin');
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');

    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->inStatus($open)->create();
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closedPositive->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

// ---------------------------------------------------------------------------
// AC-012 — closed_negative and pending stay reachable: the guard is scoped
// to exactly the two reserved phases, nothing else
// ---------------------------------------------------------------------------

it('AC-012: the creator on an unencumbered Task PATCHes to closed_negative or pending: 200, the guard does not apply outside D-4', function (TaskStatusGroup $group) {
    $creator = taskActorWith(['update']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->forCreator($creator)->inStatus($open)->create();
    $target = TaskStatus::factory()->group($group)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertOk()->assertJsonPath('data.task_status_id', $target->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $target->id]);
})->with([
    'closed_negative' => [TaskStatusGroup::ClosedNegative],
    'pending' => [TaskStatusGroup::Pending],
]);

// ---------------------------------------------------------------------------
// AC-013 — the rule is evaluated only when task_status_id is DIRTY
// ---------------------------------------------------------------------------

it('AC-013: a PATCH that does not submit task_status_id on an already closed_positive Task is not refused by D-4', function () {
    $creator = taskActorWith(['update']);
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    $task = Task::factory()->forCreator($creator)->inStatus($closedPositive)
        ->create(['closure_feedback' => 'Motivazione originale.']);
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['closure_feedback' => 'Motivazione aggiornata.'])
        ->assertOk()->assertJsonPath('data.closure_feedback', 'Motivazione aggiornata.');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_status_id' => $closedPositive->id,
        'closure_feedback' => 'Motivazione aggiornata.',
    ]);
});
