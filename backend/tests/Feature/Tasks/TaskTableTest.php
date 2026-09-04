<?php

use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The `tasks` grid (spec 0101, AC-070..AC-073)
|--------------------------------------------------------------------------
|
| Everything the grid does comes from the generic engine: no endpoint here is
| specific to Tasks. Each filter of AC-071 is asserted the only way that
| cannot pass vacuously — a matching row AND a non-matching row, with the
| result compared to the exact expected set.
|
| AC-073 (no whereRaw/orderByRaw built from input) is a repository grep and
| lives with the other greps in TaskModuleHygieneTest.
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

if (! function_exists('taskGridTitles')) {
    /**
     * @param  array<string, mixed>  $filterModel
     * @return array<int, string>
     */
    function taskGridTitles(array $filterModel): array
    {
        $items = test()->postJson('/api/tables/tasks/rows', [
            'startRow' => 0, 'endRow' => 50, 'filterModel' => $filterModel,
        ])->assertOk()->json('items');

        return collect($items)->pluck('title')->sort()->values()->all();
    }
}

// ---------------------------------------------------------------------------
// AC-070 — search, sort, server paging, column picking, preferences, export
// ---------------------------------------------------------------------------

it('AC-070: the grid searches, sorts and pages server-side with no dedicated endpoint', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->forCreator($actor)->create(['title' => 'Alfa contatto']);
    Task::factory()->forCreator($actor)->create(['title' => 'Beta contatto']);
    Task::factory()->forCreator($actor)->create(['title' => 'Gamma altro']);
    Sanctum::actingAs($actor);

    $searched = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'search' => 'contatto',
    ])->assertOk();

    expect(collect($searched->json('items'))->pluck('title')->sort()->values()->all())
        ->toBe(['Alfa contatto', 'Beta contatto']);

    $sorted = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'title', 'sort' => 'desc']],
    ])->assertOk();

    expect(collect($sorted->json('items'))->pluck('title')->all())
        ->toBe(['Gamma altro', 'Beta contatto', 'Alfa contatto']);

    $paged = $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 2, 'sortModel' => [['colId' => 'title', 'sort' => 'asc']],
    ])->assertOk();

    expect($paged->json('items'))->toHaveCount(2)
        ->and($paged->json('pagination.total'))->toBe(3);
});

it('AC-070: the column config exposes the frozen filter catalogue', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/tasks/columns')->assertOk()->json('data');
    $filterColumnIds = collect($data['filters'])->pluck('columnId')->all();

    expect($filterColumnIds)->toEqualCanonicalizing([
        'title', 'registry', 'task_type', 'task_status', 'task_priority', 'task_importance',
        'task_category', 'start_date', 'end_date', 'completion_date', 'requester', 'creator',
        'assignees', 'watchers', 'opportunity', 'work_order', 'completion_percentage',
        'estimated_minutes', 'is_blocked', 'has_subtasks', 'is_subtask',
    ]);
});

it('AC-070: the actor can save, read back and reset a column preference on the tasks domain', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    expect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.customized'))->toBeFalse();

    $this->postJson('/api/tables/tasks/preferences', ['columns' => [['id' => 'title', 'width' => 400]]])
        ->assertOk()
        ->assertJsonPath('data.customized', true);

    expect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.customized'))->toBeTrue();

    $this->deleteJson('/api/tables/tasks/preferences')->assertNoContent();

    expect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.customized'))->toBeFalse();
});

it('AC-070: the actor can save and reset the applied filter state on the tasks domain', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    expect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.filtersCustomized'))->toBeFalse();

    $this->postJson('/api/tables/tasks/filters', [
        'filterModel' => ['is_blocked' => ['filterType' => 'boolean', 'filter' => true]],
    ])->assertOk()->assertJsonPath('data.filtersCustomized', true);

    $this->deleteJson('/api/tables/tasks/filters')->assertNoContent();

    expect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.filtersCustomized'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// AC-071 — the five configurator set filters
// ---------------------------------------------------------------------------

/**
 * The five badge configurators: grid column id, Task FK, model class.
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('taskBadgeColumns', [
    'task_status' => ['task_status', 'task_status_id', TaskStatus::class],
    'task_type' => ['task_type', 'task_type_id', TaskType::class],
    'task_category' => ['task_category', 'task_category_id', TaskCategory::class],
    'task_priority' => ['task_priority', 'task_priority_id', TaskPriority::class],
    'task_importance' => ['task_importance', 'task_importance_id', TaskImportance::class],
]);

it('AC-071: each configurator set filter returns exactly the matching rows', function (string $columnId, string $foreignKey, string $modelClass) {
    $actor = taskActorWith(['viewAny', 'view']);
    $wanted = $modelClass::factory()->create(['name' => 'Cercata']);
    $other = $modelClass::factory()->create(['name' => 'Ignorata']);
    Task::factory()->forCreator($actor)->create(['title' => 'Match', $foreignKey => $wanted->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Altro', $foreignKey => $other->id]);
    Sanctum::actingAs($actor);

    expect(taskGridTitles([$columnId => ['filterType' => 'set', 'values' => ['Cercata']]]))->toBe(['Match']);
})->with('taskBadgeColumns');

it('AC-072: the five configurator columns are rendered from the configured color, icon and label', function (string $columnId, string $foreignKey, string $modelClass) {
    $actor = taskActorWith(['viewAny', 'view']);
    $lookup = $modelClass::factory()->create(['name' => 'Etichetta', 'color' => 'teal', 'icon' => 'flag']);
    $task = Task::factory()->forCreator($actor)->create([$foreignKey => $lookup->id]);
    Sanctum::actingAs($actor);

    $row = collect($this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $task->id);

    expect($row[$columnId])->toMatchArray(['id' => $lookup->id, 'name' => 'Etichetta', 'color' => 'teal', 'icon' => 'flag']);

    // The badge follows the configurator: recolouring the row changes the
    // grid with no code change (AC-072).
    $lookup->update(['color' => 'violet']);

    $recoloured = collect($this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'))
        ->firstWhere('id', $task->id);

    expect($recoloured[$columnId]['color'])->toBe('violet');
})->with('taskBadgeColumns');

// ---------------------------------------------------------------------------
// AC-071 — the record-link and user set filters
// ---------------------------------------------------------------------------

it('AC-071: the registry, opportunity and work order set filters each return the matching rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $registry = Registry::factory()->create(['name' => 'Anagrafica Alfa']);
    $opportunity = Opportunity::factory()->create(['name' => 'Opportunita Alfa']);
    $workOrder = WorkOrder::factory()->create(['title' => 'Commessa Alfa']);

    Task::factory()->forCreator($actor)->create(['title' => 'Con anagrafica', 'registry_id' => $registry->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Con opportunita', 'opportunity_id' => $opportunity->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Con commessa', 'work_order_id' => $workOrder->id]);
    Task::factory()->forCreator($actor)->create(['title' => 'Senza legami']);
    Sanctum::actingAs($actor);

    expect(taskGridTitles(['registry' => ['filterType' => 'set', 'values' => ['Anagrafica Alfa']]]))->toBe(['Con anagrafica'])
        ->and(taskGridTitles(['opportunity' => ['filterType' => 'set', 'values' => ['Opportunita Alfa']]]))->toBe(['Con opportunita'])
        ->and(taskGridTitles(['work_order' => ['filterType' => 'set', 'values' => ['Commessa Alfa']]]))->toBe(['Con commessa']);
});

it('AC-071: the requester, creator, assignee and watcher set filters each return the matching rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $requester = User::factory()->create(['name' => 'Rita Richiedente']);
    $creator = User::factory()->create(['name' => 'Carlo Creatore']);
    $assignee = User::factory()->create(['name' => 'Anna Assegnataria']);
    $watcher = User::factory()->create(['name' => 'Otto Osservatore']);

    Task::factory()->forCreator($actor)->create(['title' => 'Con richiedente', 'requester_id' => $requester->id]);
    Task::factory()->forCreator($creator)->create(['title' => 'Con creatore']);
    Task::factory()->forCreator($actor)->create(['title' => 'Con assegnatario'])->assignees()->attach($assignee->id);
    Task::factory()->forCreator($actor)->create(['title' => 'Con osservatore'])->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);

    expect(taskGridTitles(['requester' => ['filterType' => 'set', 'values' => ['Rita Richiedente']]]))->toBe(['Con richiedente'])
        ->and(taskGridTitles(['creator' => ['filterType' => 'set', 'values' => ['Carlo Creatore']]]))->toBe(['Con creatore'])
        ->and(taskGridTitles(['assignees' => ['filterType' => 'set', 'values' => ['Anna Assegnataria']]]))->toBe(['Con assegnatario'])
        ->and(taskGridTitles(['watchers' => ['filterType' => 'set', 'values' => ['Otto Osservatore']]]))->toBe(['Con osservatore']);
});

// ---------------------------------------------------------------------------
// AC-071 — boolean, date-range and hierarchy filters
// ---------------------------------------------------------------------------

it('AC-071: the is_blocked boolean filter separates blocked from non-blocked', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->forCreator($actor)->create(['title' => 'Bloccata', 'is_blocked' => true]);
    Task::factory()->forCreator($actor)->create(['title' => 'Libera', 'is_blocked' => false]);
    Sanctum::actingAs($actor);

    expect(taskGridTitles(['is_blocked' => ['filterType' => 'boolean', 'filter' => true]]))->toBe(['Bloccata'])
        ->and(taskGridTitles(['is_blocked' => ['filterType' => 'boolean', 'filter' => false]]))->toBe(['Libera']);
});

it('AC-071: the three date columns each filter by range', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Task::factory()->forCreator($actor)->create([
        'title' => 'Dentro', 'start_date' => '2026-09-10', 'end_date' => '2026-09-12', 'completion_date' => '2026-09-14',
    ]);
    Task::factory()->forCreator($actor)->create([
        'title' => 'Fuori', 'start_date' => '2026-10-10', 'end_date' => '2026-10-12', 'completion_date' => '2026-10-14',
    ]);
    Sanctum::actingAs($actor);

    $range = fn (string $from, string $to): array => ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => $from, 'dateTo' => $to];

    expect(taskGridTitles(['start_date' => $range('2026-09-01', '2026-09-30')]))->toBe(['Dentro'])
        ->and(taskGridTitles(['end_date' => $range('2026-09-01', '2026-09-30')]))->toBe(['Dentro'])
        ->and(taskGridTitles(['completion_date' => $range('2026-09-01', '2026-09-30')]))->toBe(['Dentro']);
});

it('AC-071: has_subtasks and is_subtask split parents from children', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $parent = Task::factory()->forCreator($actor)->create(['title' => 'Padre']);
    Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'Figlio']);
    Task::factory()->forCreator($actor)->create(['title' => 'Isolata']);
    Sanctum::actingAs($actor);

    expect(taskGridTitles(['has_subtasks' => ['filterType' => 'boolean', 'filter' => true]]))->toBe(['Padre'])
        ->and(taskGridTitles(['has_subtasks' => ['filterType' => 'boolean', 'filter' => false]]))->toBe(['Figlio', 'Isolata'])
        ->and(taskGridTitles(['is_subtask' => ['filterType' => 'boolean', 'filter' => true]]))->toBe(['Figlio'])
        ->and(taskGridTitles(['is_subtask' => ['filterType' => 'boolean', 'filter' => false]]))->toBe(['Isolata', 'Padre']);
});

it('AC-071: distinct values for a configurator column list only the values present in the visible rows', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $used = TaskPriority::factory()->create(['name' => 'Alta']);
    TaskPriority::factory()->create(['name' => 'Mai usata']);
    Task::factory()->forCreator($actor)->create(['task_priority_id' => $used->id]);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/tasks/values', ['columnId' => 'task_priority', 'limit' => 25])
        ->assertOk()->json('data.values');

    expect($values)->toBe(['Alta']);
});
