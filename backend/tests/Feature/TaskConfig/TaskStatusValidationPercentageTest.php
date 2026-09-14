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
| D-5 — in_validation statuses are always 100% (spec 0126, AC-012)
|--------------------------------------------------------------------------
|
| Four angles: (a) the data migration forces every in_validation row to 100
| and its down() restores the two catalogue names it can honestly know
| (2026_09_14_140000_set_in_validation_task_statuses_completion_percentage's
| own docblock documents why it is not a full inverse); (b) a Task sitting in
| that phase projects 100 through the existing TaskResource projection
| (D-6, no new code — this pins the invariant, it does not re-test D-6);
| (c) Store/UpdateTaskStatusRequest reject any in_validation row that is not
| 100; (d) covered by (a)'s down() assertions.
|
| RefreshDatabase, not DatabaseMigrations: this migration only UPDATEs rows,
| it never alters the schema, so replaying it inside the test's own
| transaction (as RewardStatusReshapeMigrationTest does for its ALTERing
| migration) needs none of that trait's heavier migrate:fresh-per-test cost.
*/

const IN_VALIDATION_MIGRATION = 'migrations/2026_09_14_140000_set_in_validation_task_statuses_completion_percentage.php';

if (! function_exists('taskStatusSystemActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskStatusSystemActorWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("task-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("task-statuses.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('replayInValidationPercentageMigrationUp')) {
    function replayInValidationPercentageMigrationUp(): void
    {
        (require database_path(IN_VALIDATION_MIGRATION))->up();
    }
}

if (! function_exists('replayInValidationPercentageMigrationDown')) {
    function replayInValidationPercentageMigrationDown(): void
    {
        (require database_path(IN_VALIDATION_MIGRATION))->down();
    }
}

// ---------------------------------------------------------------------------
// AC-012(a)/(d) — the data migration itself
// ---------------------------------------------------------------------------

it('AC-012: up() forces every in_validation row to 100, whatever it held before', function () {
    $preanalisi = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(30)->create(['name' => 'Preanalisi da validare']);
    $esecuzione = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(80)->create(['name' => 'Esecuzione da validare']);
    $custom = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(55)->create(['name' => 'In validazione custom']);
    $untouched = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(20)->create();

    replayInValidationPercentageMigrationUp();

    expect($preanalisi->fresh()->completion_percentage)->toBe(100)
        ->and($esecuzione->fresh()->completion_percentage)->toBe(100)
        ->and($custom->fresh()->completion_percentage)->toBe(100)
        ->and($untouched->fresh()->completion_percentage)->toBe(20);
});

it('AC-012: down() restores the two catalogue names by their known percentages, and leaves a custom row at 100', function () {
    $preanalisi = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(100)->create(['name' => 'Preanalisi da validare']);
    $esecuzione = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(100)->create(['name' => 'Esecuzione da validare']);
    $custom = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(100)->create(['name' => 'In validazione custom']);

    replayInValidationPercentageMigrationDown();

    expect($preanalisi->fresh()->completion_percentage)->toBe(30)
        ->and($esecuzione->fresh()->completion_percentage)->toBe(80)
        // Known limitation documented in the migration's own docblock: a
        // custom row's PRE-migration value is not recoverable, so it rolls
        // back at 100, not at whatever it held before up() ran.
        ->and($custom->fresh()->completion_percentage)->toBe(100);
});

it('AC-012: down() never touches a row outside the in_validation phase, even if it carries a catalogue name', function () {
    $reused = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(100)->create(['name' => 'Preanalisi da validare']);

    replayInValidationPercentageMigrationDown();

    expect($reused->fresh()->completion_percentage)->toBe(100);
});

// ---------------------------------------------------------------------------
// AC-012(b) — a Task in that phase exposes 100 (projection, D-6)
// ---------------------------------------------------------------------------

it('AC-012: a Task in an in_validation status exposes completion_percentage 100', function () {
    Permission::findOrCreate('tasks.view');
    Permission::findOrCreate('tasks.viewAll');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['tasks.view', 'tasks.viewAll']);

    $status = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(100)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($status)->create();

    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 100);
});

// ---------------------------------------------------------------------------
// AC-012(c) — Store/UpdateTaskStatusRequest reject a non-100 in_validation row
// ---------------------------------------------------------------------------

it('AC-012: POST task-statuses with group in_validation and percentage 80 is 422 on completion_percentage', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['create']));

    $this->postJson('/api/task-statuses', [
        'name' => 'Da validare',
        'color' => 'amber',
        'group' => TaskStatusGroup::InValidation->value,
        'completion_percentage' => 80,
    ])->assertStatus(422)->assertJsonValidationErrors('completion_percentage');

    expect(TaskStatus::query()->where('name', 'Da validare')->exists())->toBeFalse();
});

it('AC-012: POST task-statuses with group in_validation and percentage 100 is created', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['create']));

    $this->postJson('/api/task-statuses', [
        'name' => 'Da validare',
        'color' => 'amber',
        'group' => TaskStatusGroup::InValidation->value,
        'completion_percentage' => 100,
    ])->assertCreated()->assertJsonPath('data.completion_percentage', 100);
});

it('AC-012: PUT changing only completion_percentage to 80 on an in_validation status is 422, group not resent', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $status = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->completion(100)->create();

    $this->patchJson("/api/task-statuses/{$status->id}", ['completion_percentage' => 80])
        ->assertStatus(422)->assertJsonValidationErrors('completion_percentage');

    expect($status->fresh()->completion_percentage)->toBe(100);
});

it('AC-012: PUT moving group to in_validation with percentage 100 is 200', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $status = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(20)->create();

    $this->patchJson("/api/task-statuses/{$status->id}", [
        'group' => TaskStatusGroup::InValidation->value,
        'completion_percentage' => 100,
    ])->assertOk()->assertJsonPath('data.completion_percentage', 100);
});

it('AC-012: PUT moving group to in_validation without resending completion_percentage is 422 when the persisted value is not 100', function () {
    Sanctum::actingAs(taskStatusSystemActorWith(['update']));
    $status = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(20)->create();

    $this->patchJson("/api/task-statuses/{$status->id}", ['group' => TaskStatusGroup::InValidation->value])
        ->assertStatus(422)->assertJsonValidationErrors('completion_percentage');

    expect($status->fresh()->group)->toBe(TaskStatusGroup::Open);
});
