<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The `tasks` grid advanced filters (spec 0147, AC-001/AC-002; spec 0153,
| D-1)
|--------------------------------------------------------------------------
|
| The same axes as the work-order Task board, applied server-side. Each case
| creates a matching AND a non-matching task and compares the exact set.
| "Today" is pinned to Wednesday 2026-09-23 (ISO week 21..27).
|
| `assignment` is now REQUIRED (spec 0153, D-1): every case below that is NOT
| itself about `assignment` passes `assignment: [all]` explicitly and gives
| the actor a role (as a watcher — neutral to every other assertion here) on
| each fixture task, so the mandatory filter never silently hides a row a
| test's OWN axis expects to see.
*/

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) across the suites that need it (see TaskTableTest).
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

/**
 * `assignment: [all]` is the default (spec 0153, D-1): the caller's own
 * `$advancedFilters` overrides it whenever the case is about `assignment`
 * itself.
 *
 * @param  array<string, mixed>  $advancedFilters
 * @return array<int, string>
 */
function taskTitlesWithAdvancedFilters(array $advancedFilters): array
{
    $payload = [
        'startRow' => 0, 'endRow' => 50,
        'advancedFilters' => ['assignment' => ['all'], ...$advancedFilters],
    ];

    $items = test()->postJson('/api/tables/tasks/rows', $payload)->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

/**
 * `$actor`, when given, is attached as a WATCHER so the mandatory
 * `assignment` filter's `all` default (a role union) sees the row — a role
 * that does not interfere with any status/due/relation assertion in this
 * file.
 */
function filterableTaskInGroup(string $title, TaskStatusGroup $group, array $attributes = [], ?User $actor = null): Task
{
    $status = TaskStatus::factory()->group($group)->create();
    $task = Task::factory()->inStatus($status)->create(['title' => $title, ...$attributes]);

    if ($actor !== null) {
        $task->watchers()->attach($actor->id);
    }

    return $task;
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exposes the board filters in the tasks config, status/assignment required (AC-001, spec 0153 D-1)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));

    $filters = collect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.advancedFilters'))->keyBy('name');

    expect($filters->keys()->all())->toBe([
        'status', 'due', 'assignment', 'task_status', 'task_type', 'task_priority', 'task_importance',
        'requester', 'assignees', 'watchers',
    ])
        ->and($filters['status']['required'])->toBeTrue()
        ->and($filters['status']['defaultValue'])->toBe('open')
        ->and($filters['status']['enumKey'])->toBe('task_list_status')
        ->and($filters['assignment']['required'])->toBeTrue()
        ->and($filters['assignment']['multiple'])->toBeTrue()
        ->and($filters['assignment']['defaultValue'])->toBe(['assigned_to_me'])
        ->and($filters['assignment']['enumKey'])->toBe('task_assignment_scope')
        ->and($filters['assignees']['source'])->toBe(['resource' => 'users']);
});

it('shows only open tasks when the request omits status, every task with status all (AC-002)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('open', TaskStatusGroup::Open, actor: $actor);
    filterableTaskInGroup('pending', TaskStatusGroup::Pending, actor: $actor);
    filterableTaskInGroup('validating', TaskStatusGroup::InValidation, actor: $actor);
    filterableTaskInGroup('won', TaskStatusGroup::ClosedPositive, actor: $actor);
    filterableTaskInGroup('lost', TaskStatusGroup::ClosedNegative, actor: $actor);

    expect(taskTitlesWithAdvancedFilters([]))->toBe(['open', 'pending', 'validating'])
        ->and(taskTitlesWithAdvancedFilters(['status' => 'all']))->toBe(['lost', 'open', 'pending', 'validating', 'won'])
        ->and(taskTitlesWithAdvancedFilters(['status' => 'completed']))->toBe(['lost', 'won']);
});

it('status blocked keeps only blocked tasks, whatever their phase (AC-001)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('blocked', TaskStatusGroup::Open, ['is_blocked' => true], actor: $actor);
    filterableTaskInGroup('free', TaskStatusGroup::Open, actor: $actor);

    expect(taskTitlesWithAdvancedFilters(['status' => 'blocked']))->toBe(['blocked']);
});

it('due filters on end date falling back to start date (AC-001)', function (string $window, array $expected) {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('today-end', TaskStatusGroup::Open, ['start_date' => '2026-09-01', 'end_date' => '2026-09-23'], $actor);
    filterableTaskInGroup('today-start-only', TaskStatusGroup::Open, ['start_date' => '2026-09-23', 'end_date' => null], $actor);
    filterableTaskInGroup('yesterday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-22'], $actor);
    filterableTaskInGroup('sunday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-27'], $actor);
    filterableTaskInGroup('next-monday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-28'], $actor);
    filterableTaskInGroup('no-date', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => null], $actor);

    expect(taskTitlesWithAdvancedFilters(['due' => $window]))->toBe($expected);
})->with([
    ['today', ['today-end', 'today-start-only']],
    ['overdue', ['yesterday']],
    ['this_week', ['sunday', 'today-end', 'today-start-only', 'yesterday']],
]);

it('assignment narrows to the tasks assigned to or requested by the actor (AC-001)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('assigned', TaskStatusGroup::Open)->assignees()->attach($actor->id);
    filterableTaskInGroup('requested', TaskStatusGroup::Open, ['requester_id' => $actor->id]);
    filterableTaskInGroup('other', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['assigned_to_me']]))->toBe(['assigned'])
        ->and(taskTitlesWithAdvancedFilters(['assignment' => ['requested_by_me']]))->toBe(['requested']);
});

// spec 0153, D-1: multiple values combine in OR.
it('assignment combines several values in OR (spec 0153, D-1, AC-001)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('assigned', TaskStatusGroup::Open)->assignees()->attach($actor->id);
    filterableTaskInGroup('watched', TaskStatusGroup::Open)->watchers()->attach($actor->id);
    filterableTaskInGroup('other', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['assigned_to_me', 'observed_by_me']]))
        ->toBe(['assigned', 'watched']);
});

// spec 0153, D-1: `all` is the union of every role, even for an actor with
// `tasks.viewAll` — it still narrows, it never lifts the filter.
it('assignment "all" is the union of every role, even with tasks.viewAll (spec 0153, D-1, AC-001)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('assigned', TaskStatusGroup::Open)->assignees()->attach($actor->id);
    filterableTaskInGroup('requested', TaskStatusGroup::Open, ['requester_id' => $actor->id]);
    filterableTaskInGroup('watched', TaskStatusGroup::Open)->watchers()->attach($actor->id);
    filterableTaskInGroup('created', TaskStatusGroup::Open, ['creator_id' => $actor->id]);
    filterableTaskInGroup('unrelated', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['all']]))
        ->toBe(['assigned', 'created', 'requested', 'watched']);
});

// spec 0153, D-1 (user decision 2026-09-24): `visible` lifts the role
// restriction, leaving only TaskVisibilityScope.
it('assignment "visible" lists every task the visibility scope allows (spec 0153, D-1)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('assigned', TaskStatusGroup::Open)->assignees()->attach($actor->id);
    filterableTaskInGroup('unrelated', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['visible']]))->toBe(['assigned', 'unrelated'])
        ->and(taskTitlesWithAdvancedFilters(['assignment' => ['assigned_to_me', 'visible']]))->toBe(['assigned', 'unrelated']);
});

it('assignment "visible" coincides with "all" without tasks.viewAll (spec 0153, D-1)', function () {
    $actor = taskActorWith(['viewAny'], withViewAll: false);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('watched', TaskStatusGroup::Open)->watchers()->attach($actor->id);
    filterableTaskInGroup('unrelated', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['visible']]))->toBe(['watched']);
});

it('offers "visible" only to actors who can see beyond their own roles (spec 0153, D-1)', function (bool $viewAll, array $excluded) {
    Sanctum::actingAs(taskActorWith(['viewAny'], withViewAll: $viewAll));

    $assignment = collect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.advancedFilters'))
        ->firstWhere('name', 'assignment');

    expect($assignment['type'])->toBe('enum')
        ->and($assignment['multiple'])->toBeTrue()
        ->and($assignment['excludedValues'])->toBe($excluded);
})->with([
    'with viewAll' => [true, []],
    'without' => [false, ['visible']],
]);

// contract: an empty `assignment` array is a 422 (required, non-empty list).
it('an empty assignment array answers 422 (spec 0153, D-1 contract)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['assignment' => []],
    ])->assertUnprocessable()->assertJsonValidationErrors('advancedFilters.assignment');
});

it('status in_validation keeps only tasks in that phase (spec 0151, AC-006)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('open', TaskStatusGroup::Open, actor: $actor);
    filterableTaskInGroup('validating', TaskStatusGroup::InValidation, actor: $actor);
    filterableTaskInGroup('won', TaskStatusGroup::ClosedPositive, actor: $actor);

    expect(taskTitlesWithAdvancedFilters(['status' => 'in_validation']))->toBe(['validating']);
});

it('assignment assigned_by_me is exclusive of assigned_to_me (spec 0151, D-3/AC-006)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('requested-only', TaskStatusGroup::Open, ['requester_id' => $actor->id]);
    $requestedAndAssigned = filterableTaskInGroup('requested-and-assigned', TaskStatusGroup::Open, ['requester_id' => $actor->id]);
    $requestedAndAssigned->assignees()->attach($actor->id);
    filterableTaskInGroup('other', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['assigned_by_me']]))->toBe(['requested-only']);
});

it('assignment created_by_me excludes tasks the actor also requested, assigned or watches (spec 0151 D-3, spec 0153 D-3)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('created-no-requester', TaskStatusGroup::Open, ['creator_id' => $actor->id, 'requester_id' => null]);
    filterableTaskInGroup('created-other-requester', TaskStatusGroup::Open, ['creator_id' => $actor->id]);
    filterableTaskInGroup('created-self-requested', TaskStatusGroup::Open, ['creator_id' => $actor->id, 'requester_id' => $actor->id]);
    $createdAndAssigned = filterableTaskInGroup('created-and-assigned', TaskStatusGroup::Open, ['creator_id' => $actor->id]);
    $createdAndAssigned->assignees()->attach($actor->id);
    $createdAndWatched = filterableTaskInGroup('created-and-watched', TaskStatusGroup::Open, ['creator_id' => $actor->id]);
    $createdAndWatched->watchers()->attach($actor->id);
    filterableTaskInGroup('other', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['created_by_me']]))
        ->toBe(['created-no-requester', 'created-other-requester']);
});

it('assignment observed_by_me keeps only tasks the actor watches (spec 0151, D-3/AC-006)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    filterableTaskInGroup('watched', TaskStatusGroup::Open)->watchers()->attach($actor->id);
    filterableTaskInGroup('other', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['assignment' => ['observed_by_me']]))->toBe(['watched']);
});

it('each configurator relation filter returns exactly the matching tasks (AC-001)', function (string $filter, string $foreignKey, string $modelClass) {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    $wanted = $modelClass::factory()->create();
    $other = $modelClass::factory()->create();
    Task::factory()->create(['title' => 'match', $foreignKey => $wanted->id])->watchers()->attach($actor->id);
    Task::factory()->create(['title' => 'miss', $foreignKey => $other->id])->watchers()->attach($actor->id);

    expect(taskTitlesWithAdvancedFilters([$filter => [$wanted->id], 'status' => 'all']))->toBe(['match']);
})->with([
    ['task_status', 'task_status_id', TaskStatus::class],
    ['task_type', 'task_type_id', TaskType::class],
    ['task_priority', 'task_priority_id', TaskPriority::class],
    ['task_importance', 'task_importance_id', TaskImportance::class],
]);

it('the people filters match requester, any assignee and any watcher (AC-001)', function () {
    $actor = taskActorWith(['viewAny']);
    Sanctum::actingAs($actor);
    $person = User::factory()->create();
    Task::factory()->create(['title' => 'requested', 'requester_id' => $person->id])->watchers()->attach($actor->id);
    Task::factory()->create(['title' => 'assigned'])->assignees()->attach([$person->id, $actor->id]);
    Task::factory()->create(['title' => 'watched'])->watchers()->attach([$person->id, $actor->id]);
    Task::factory()->create(['title' => 'unrelated'])->watchers()->attach($actor->id);

    expect(taskTitlesWithAdvancedFilters(['requester' => [$person->id]]))->toBe(['requested'])
        ->and(taskTitlesWithAdvancedFilters(['assignees' => [$person->id]]))->toBe(['assigned'])
        ->and(taskTitlesWithAdvancedFilters(['watchers' => [$person->id]]))->toBe(['watched']);
});

it('rejects an advanced filter outside the catalogue with 422 (AC-001)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['title' => 'x'],
    ])->assertUnprocessable();
});
