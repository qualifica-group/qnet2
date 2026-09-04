<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\ExportRun;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Derived completion percentage (spec 0101, D-6, AC-020..AC-023)
|--------------------------------------------------------------------------
|
| `tasks` has no `completion_percentage` column: the value is a PROJECTION of
| the Task's status, resolved at response time. Every assertion below is
| therefore written as "change the status side, observe the Task side, with
| no write to `tasks` in between" — the only shape that can actually catch a
| denormalised copy of the value.
|
| AC-024 (no production file conditions on a status LABEL) is a repository
| grep and lives with the other greps in TaskModuleHygieneTest.
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

if (! function_exists('taskConfigActorWith')) {
    /**
     * An actor holding $abilities on ONE of the five task configurators.
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
     *
     * @param  array<int, string>  $abilities
     */
    function taskConfigActorWith(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

it('AC-020: TaskResource.completion_percentage equals the status percentage', function () {
    $actor = taskActorWith(['view']);
    $status = TaskStatus::factory()->completion(35)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($status)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 35)
        ->assertJsonPath('data.task_status.completion_percentage', 35);
});

it('AC-020: moving the Task to another status changes the percentage with no write to tasks', function () {
    $actor = taskActorWith(['view', 'update']);
    $quarter = TaskStatus::factory()->completion(25)->create();
    $threeQuarters = TaskStatus::factory()->completion(75)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($quarter)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.completion_percentage', 25);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $threeQuarters->id])
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 75);

    // The percentage lives on the status, so `tasks` never gained a column
    // that could hold a stale copy of it.
    expect(array_key_exists('completion_percentage', $task->fresh()->getAttributes()))->toBeFalse();
});

it('AC-021: editing a status percentage moves EVERY task in that status, with no backfill', function () {
    $actor = taskActorWith(['view', 'update']);
    $configuratorActor = taskConfigActorWith('task-statuses', ['update']);
    $status = TaskStatus::factory()->completion(10)->create();
    $first = Task::factory()->forCreator($actor)->inStatus($status)->create();
    $second = Task::factory()->forCreator($actor)->inStatus($status)->create();
    $untouched = Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(60)->create())->create();

    Sanctum::actingAs($configuratorActor);
    $this->patchJson("/api/task-statuses/{$status->id}", ['completion_percentage' => 90])->assertOk();

    Sanctum::actingAs($actor);
    $this->getJson("/api/tasks/{$first->id}")->assertOk()->assertJsonPath('data.completion_percentage', 90);
    $this->getJson("/api/tasks/{$second->id}")->assertOk()->assertJsonPath('data.completion_percentage', 90);
    $this->getJson("/api/tasks/{$untouched->id}")->assertOk()->assertJsonPath('data.completion_percentage', 60);
});

// ---------------------------------------------------------------------------
// AC-022 — the derived grid column: sortable, and present in the export
// ---------------------------------------------------------------------------

it('AC-022: the completion_percentage grid column is declared and sortable', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.columns'))->keyBy('id');

    expect($columns)->toHaveKey('completion_percentage')
        ->and($columns['completion_percentage']['sortable'])->toBeTrue();
});

it('AC-022: sorting the grid by completion_percentage matches sorting by the status percentage', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(80)->create())->create(['title' => 'Ottanta']);
    Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(10)->create())->create(['title' => 'Dieci']);
    Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(45)->create())->create(['title' => 'Quarantacinque']);
    Sanctum::actingAs($actor);

    $ascending = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(collect($ascending)->pluck('title')->all())->toBe(['Dieci', 'Quarantacinque', 'Ottanta'])
        ->and(collect($ascending)->pluck('completion_percentage')->all())->toBe([10, 45, 80]);

    $descending = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => 'desc']],
    ])->assertOk()->json('items');

    expect(collect($descending)->pluck('title')->all())->toBe(['Ottanta', 'Quarantacinque', 'Dieci']);
});

it('AC-022: the export carries the derived column', function () {
    Storage::fake('local');
    $actor = taskActorWith(['viewAny', 'view', 'export']);
    Task::factory()->forCreator($actor)->inStatus(TaskStatus::factory()->completion(45)->create())->create(['title' => 'Esportata']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [
            ['colId' => 'title', 'header' => 'Title'],
            ['colId' => 'completion_percentage', 'header' => 'Completion'],
        ],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();
    $csv = Storage::disk('local')->get($run->file_path);

    expect($csv)->toContain('Esportata')->toContain('45');
});

// ---------------------------------------------------------------------------
// AC-023 — the LABEL is not part of any rule: rename it, nothing changes
// ---------------------------------------------------------------------------

it('AC-023: renaming closed_positive leaves the percentage, the closure guard and the filter identical', function () {
    $actor = taskActorWith(['viewAny', 'view', 'update']);
    $closedPositive = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();

    $assertRulesHold = function () use ($actor, $closedPositive): void {
        Sanctum::actingAs($actor);

        // 1. percentage is still 100 and still derived from the status.
        $task = Task::factory()->forCreator($actor)->inStatus($closedPositive)->create();
        $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('data.completion_percentage', 100);

        // 2. the closure feedback guard still fires on this status (D-7).
        $needsFeedback = Task::factory()->forCreator($actor)->requiringClosureFeedback()
            ->inStatus(TaskStatus::factory()->completion(0)->create())->create();
        $this->patchJson("/api/tasks/{$needsFeedback->id}", ['task_status_id' => $closedPositive->id])
            ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');

        // 3. the grid set filter still selects the same rows: it reads the
        //    status' CURRENT label, it does not carry a hardcoded one.
        $rows = $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25,
            'filterModel' => ['task_status' => ['filterType' => 'set', 'values' => [$closedPositive->fresh()->name]]],
        ])->assertOk()->json('items');

        expect(collect($rows)->pluck('id'))->toContain($task->id)
            ->and(collect($rows)->pluck('id'))->not->toContain($needsFeedback->id);
    };

    $assertRulesHold();

    $closedPositive->update(['name' => 'Terminato']);
    expect($closedPositive->fresh()->name)->toBe('Terminato');

    $assertRulesHold();
});
