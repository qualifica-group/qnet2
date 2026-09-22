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
| The `tasks` grid advanced filters (spec 0147, AC-001/AC-002)
|--------------------------------------------------------------------------
|
| The same axes as the work-order Task board, applied server-side. Each case
| creates a matching AND a non-matching task and compares the exact set.
| "Today" is pinned to Wednesday 2026-09-23 (ISO week 21..27).
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
 * @param  array<string, mixed>  $advancedFilters
 * @return array<int, string>
 */
function taskTitlesWithAdvancedFilters(array $advancedFilters): array
{
    $payload = ['startRow' => 0, 'endRow' => 50];

    if ($advancedFilters !== []) {
        $payload['advancedFilters'] = $advancedFilters;
    }

    $items = test()->postJson('/api/tables/tasks/rows', $payload)->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

function filterableTaskInGroup(string $title, TaskStatusGroup $group, array $attributes = []): Task
{
    $status = TaskStatus::factory()->group($group)->create();

    return Task::factory()->inStatus($status)->create(['title' => $title, ...$attributes]);
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('exposes the board filters in the tasks config, status required and defaulting to open (AC-001)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));

    $filters = collect($this->getJson('/api/tables/tasks/columns')->assertOk()->json('data.advancedFilters'))->keyBy('name');

    expect($filters->keys()->all())->toBe([
        'status', 'due', 'assignment', 'task_status', 'task_type', 'task_priority', 'task_importance',
        'requester', 'assignees', 'watchers',
    ])
        ->and($filters['status']['required'])->toBeTrue()
        ->and($filters['status']['defaultValue'])->toBe('open')
        ->and($filters['status']['enumKey'])->toBe('task_list_status')
        ->and($filters['assignees']['source'])->toBe(['resource' => 'users']);
});

it('shows only open tasks when the request omits status, every task with status all (AC-002)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));
    filterableTaskInGroup('open', TaskStatusGroup::Open);
    filterableTaskInGroup('pending', TaskStatusGroup::Pending);
    filterableTaskInGroup('validating', TaskStatusGroup::InValidation);
    filterableTaskInGroup('won', TaskStatusGroup::ClosedPositive);
    filterableTaskInGroup('lost', TaskStatusGroup::ClosedNegative);

    expect(taskTitlesWithAdvancedFilters([]))->toBe(['open', 'pending', 'validating'])
        ->and(taskTitlesWithAdvancedFilters(['status' => 'all']))->toBe(['lost', 'open', 'pending', 'validating', 'won'])
        ->and(taskTitlesWithAdvancedFilters(['status' => 'completed']))->toBe(['lost', 'won']);
});

it('status blocked keeps only blocked tasks, whatever their phase (AC-001)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));
    filterableTaskInGroup('blocked', TaskStatusGroup::Open, ['is_blocked' => true]);
    filterableTaskInGroup('free', TaskStatusGroup::Open);

    expect(taskTitlesWithAdvancedFilters(['status' => 'blocked']))->toBe(['blocked']);
});

it('due filters on end date falling back to start date (AC-001)', function (string $window, array $expected) {
    Sanctum::actingAs(taskActorWith(['viewAny']));
    filterableTaskInGroup('today-end', TaskStatusGroup::Open, ['start_date' => '2026-09-01', 'end_date' => '2026-09-23']);
    filterableTaskInGroup('today-start-only', TaskStatusGroup::Open, ['start_date' => '2026-09-23', 'end_date' => null]);
    filterableTaskInGroup('yesterday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-22']);
    filterableTaskInGroup('sunday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-27']);
    filterableTaskInGroup('next-monday', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => '2026-09-28']);
    filterableTaskInGroup('no-date', TaskStatusGroup::Open, ['start_date' => null, 'end_date' => null]);

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

    expect(taskTitlesWithAdvancedFilters(['assignment' => 'assigned_to_me']))->toBe(['assigned'])
        ->and(taskTitlesWithAdvancedFilters(['assignment' => 'requested_by_me']))->toBe(['requested']);
});

it('each configurator relation filter returns exactly the matching tasks (AC-001)', function (string $filter, string $foreignKey, string $modelClass) {
    Sanctum::actingAs(taskActorWith(['viewAny']));
    $wanted = $modelClass::factory()->create();
    $other = $modelClass::factory()->create();
    Task::factory()->create(['title' => 'match', $foreignKey => $wanted->id]);
    Task::factory()->create(['title' => 'miss', $foreignKey => $other->id]);

    expect(taskTitlesWithAdvancedFilters([$filter => [$wanted->id], 'status' => 'all']))->toBe(['match']);
})->with([
    ['task_status', 'task_status_id', TaskStatus::class],
    ['task_type', 'task_type_id', TaskType::class],
    ['task_priority', 'task_priority_id', TaskPriority::class],
    ['task_importance', 'task_importance_id', TaskImportance::class],
]);

it('the people filters match requester, any assignee and any watcher (AC-001)', function () {
    Sanctum::actingAs(taskActorWith(['viewAny']));
    $person = User::factory()->create();
    Task::factory()->create(['title' => 'requested', 'requester_id' => $person->id]);
    Task::factory()->create(['title' => 'assigned'])->assignees()->attach($person->id);
    Task::factory()->create(['title' => 'watched'])->watchers()->attach($person->id);
    Task::factory()->create(['title' => 'unrelated']);

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
