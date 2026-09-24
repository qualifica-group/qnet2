<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\ExportRun;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Derived completion percentage (spec 0101, D-6, AC-020..AC-023; spec 0153,
| D-10, AC-013)
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
|
| AC-013 (spec 0153, D-10): with sub-tasks the percentage is the rounded
| average of the children's OWN percentages (recursive), capped at 99 while
| the parent is open; a closed parent is always 100, subtasks or not.
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

it('D-5: the status ref carries the PHASE, so the client can mirror D-7 off the persisted status', function () {
    $actor = taskActorWith(['view']);
    // An ORDINARY row in a closing phase: the case the client cannot decide
    // from `system_key` alone since the 2026-09-04 rectification.
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closing)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('data.task_status.group', 'closed_negative')
        ->assertJsonPath('data.task_status.system_key', null);
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

    // spec 0153, D-1: `assignment` is mandatory; `all` reaches these
    // creator-only fixtures (the actor holds no OTHER role on them).
    $ascending = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assignment' => ['all']],
        'sortModel' => [['colId' => 'completion_percentage', 'sort' => 'asc']],
    ])->assertOk()->json('items');

    expect(collect($ascending)->pluck('title')->all())->toBe(['Dieci', 'Quarantacinque', 'Ottanta'])
        ->and(collect($ascending)->pluck('completion_percentage')->all())->toBe([10, 45, 80]);

    $descending = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => ['assignment' => ['all']],
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
        // spec 0153, D-1: `assignment` is mandatory; `all` reaches this
        // creator-only fixture (the actor holds no OTHER role on it).
        'advancedFilters' => ['assignment' => ['all']],
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

        // 2. REQUIREMENT CHANGED (spec 0123, D-4): a PATCH toward
        //    closed_positive now 422s on task_status_id for every actor,
        //    feedback or not — the closure feedback guard (D-7) never even
        //    runs any more via this path (it still does via /complete). The
        //    rule this point actually pins — a rule keyed by system_key
        //    survives a rename of the row — still holds, just for D-4
        //    instead of D-7.
        $needsFeedback = Task::factory()->forCreator($actor)->requiringClosureFeedback()
            ->inStatus(TaskStatus::factory()->completion(0)->create())->create();
        $this->patchJson("/api/tasks/{$needsFeedback->id}", ['task_status_id' => $closedPositive->id])
            ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

        // 3. the grid set filter still selects the same rows: it reads the
        //    status' CURRENT label, it does not carry a hardcoded one.
        //    REQUIREMENT CHANGED (spec 0147, D-2): the grid now defaults to
        //    the open tasks, so `status: all` lifts that default to reach the
        //    closed row this point is about.
        // spec 0153, D-1: `assignment` is mandatory too; `all` reaches both
        // creator-only fixtures alongside the pre-existing `status: all`.
        $rows = $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25,
            'advancedFilters' => ['status' => 'all', 'assignment' => ['all']],
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

// ---------------------------------------------------------------------------
// AC-013 (spec 0153, D-10) — recursive, capped average over sub-tasks
// ---------------------------------------------------------------------------

it('AC-013: an open parent with two 100% children caps the average at 99', function () {
    $actor = taskActorWith(['view']);
    $openParent = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($openParent)->create();
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $parent->id]);
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 99);
});

// The percentage is a property of the task, not of the viewer: a child the
// actor cannot see still counts in the average.
it('AC-013: the average counts every child, also those the actor cannot see', function () {
    $actor = taskActorWith(['view'], withViewAll: false);
    $openParent = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($openParent)->create();
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $zero = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create();
    Task::factory()->forCreator($actor)->inStatus($closing)->create(['parent_task_id' => $parent->id]);
    Task::factory()->inStatus($zero)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.subtasks')
        ->assertJsonPath('data.completion_percentage', 50);
});

it('AC-013: an open parent with children at 50 and 100 shows the rounded average, 75', function () {
    $actor = taskActorWith(['view']);
    $openParent = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($openParent)->create();
    $half = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(50)->create();
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    Task::factory()->inStatus($half)->create(['parent_task_id' => $parent->id]);
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 75);
});

it('AC-013: a CLOSED parent is 100 regardless of its own children', function () {
    $actor = taskActorWith(['view']);
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $parent = Task::factory()->forCreator($actor)->inStatus($closing)->create();
    $stillOpen = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(10)->create();
    Task::factory()->inStatus($stillOpen)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 100);
});

it('AC-013: the average recurses through a grandchild level', function () {
    $actor = taskActorWith(['view']);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->completion(0)->create();
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $parent = Task::factory()->forCreator($actor)->inStatus($openStatus)->create();
    // Child A: open itself, with two closed grandchildren -> min(avg(100,100), 99) = 99.
    $childA = Task::factory()->inStatus($openStatus)->create(['parent_task_id' => $parent->id]);
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $childA->id]);
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $childA->id]);
    // Child B: closed outright -> 100.
    Task::factory()->inStatus($closing)->create(['parent_task_id' => $parent->id]);
    Sanctum::actingAs($actor);

    // Parent: avg(99, 100) = 99.5 -> round = 100 -> capped at 99 (still open).
    $this->getJson("/api/tasks/{$parent->id}")
        ->assertOk()
        ->assertJsonPath('data.completion_percentage', 99);
});

// The derived percentage must not cost one query per grid row.
it('AC-013: the grid computes the percentage with the same number of queries for 1 or 5 rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);
    $closing = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $queriesFor = function (int $rows) use ($actor, $closing): int {
        Task::query()->whereNotNull('parent_task_id')->delete();
        Task::query()->delete();
        foreach (range(1, $rows) as $index) {
            $parent = Task::factory()->forCreator($actor)->create();
            Task::factory()->inStatus($closing)->create(['parent_task_id' => $parent->id]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => ['visible']],
        ])->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    };

    // Warm-up: roles/permissions and catalogues are cached after the first call.
    $queriesFor(1);

    expect($queriesFor(5))->toBe($queriesFor(1));
});
