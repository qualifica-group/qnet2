<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The `tasks` grid — server-side Kanban column grouping (spec 0164)
|--------------------------------------------------------------------------
|
| `kanbanGroup` on POST /api/tables/tasks/rows narrows the SSRM query to one
| Kanban column, AFTER filterModel/advancedFilters/search (AC-001): `{by:
| 'status', key: <task_status_id>}` or `{by: 'due', key: <one of the seven
| fixed buckets>}`, the SAME classification `task-kanban-due-buckets.ts`
| computes client-side (AC-002). Every 422 branch — unsupported domain,
| unknown `by`/`key`, non-existent status id, combined with tree — is
| AC-003.
*/

if (! function_exists('taskKanbanActor')) {
    function taskKanbanActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo(['tasks.viewAny', 'tasks.view', 'tasks.viewAll']);

        return $user;
    }
}

if (! function_exists('kanbanRowTitles')) {
    /**
     * `status` => `all` lifts the Task table's own default advanced filter
     * (`TaskAdvancedFilterCatalog`'s `status` required-with-default `open`,
     * spec 0147 D-2) — orthogonal to `kanbanGroup` but AND-combined with it
     * (data_contract: "si combina con... advancedFilters... esistenti"), so
     * a Kanban column that must include closing/closed statuses (the "per
     * stato" board's closed columns, the "per scadenza" board's `completed`
     * bucket) is not silently narrowed by it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<int, string>
     */
    function kanbanRowTitles(array $overrides): array
    {
        $response = test()->postJson('/api/tables/tasks/rows', array_merge([
            'startRow' => 0, 'endRow' => 50,
            'advancedFilters' => ['assignment' => ['visible'], 'status' => 'all'],
        ], $overrides))->assertOk();

        return collect($response->json('items'))->pluck('title')->sort()->values()->all();
    }
}

// --- AC-001: by status ------------------------------------------------

it('AC-001: by status returns only the column tasks, combined with search and filters, total = column count', function () {
    $actor = taskKanbanActor();
    Sanctum::actingAs($actor);

    $statusA = TaskStatus::factory()->create();
    $statusB = TaskStatus::factory()->create();

    Task::factory()->forCreator($actor)->inStatus($statusA)->create(['title' => 'Rosso Uno']);
    Task::factory()->forCreator($actor)->inStatus($statusA)->create(['title' => 'Rosso Due']);
    Task::factory()->forCreator($actor)->inStatus($statusA)->create(['title' => 'Verde Tre']);
    Task::factory()->forCreator($actor)->inStatus($statusB)->create(['title' => 'Rosso Quattro']);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'status', 'key' => $statusA->id]]))
        ->toBe(['Rosso Due', 'Rosso Uno', 'Verde Tre']);

    $filtered = test()->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50,
        'advancedFilters' => ['assignment' => ['visible']],
        'search' => 'Rosso',
        'kanbanGroup' => ['by' => 'status', 'key' => $statusA->id],
    ])->assertOk();

    expect(collect($filtered->json('items'))->pluck('title')->sort()->values()->all())
        ->toBe(['Rosso Due', 'Rosso Uno'])
        ->and($filtered->json('pagination.total'))->toBe(2);
});

// --- AC-002: by due -----------------------------------------------------

/**
 * One fixed "today" (2026-09-16, a Wednesday) exercises every bucket,
 * including the limit cases the spec calls out: no due date at all, a
 * closed task (always `completed` regardless of its date), the ISO week's
 * own Sunday boundary, and the calendar month's last day.
 *
 * @return array<string, Task>
 */
function taskKanbanDueFixture(User $actor): array
{
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    return [
        'overdue' => Task::factory()->forCreator($actor)->create(['title' => 'Scaduto', 'end_date' => '2026-09-10']),
        'today' => Task::factory()->forCreator($actor)->create(['title' => 'Oggi', 'end_date' => '2026-09-16']),
        'tomorrow' => Task::factory()->forCreator($actor)->create(['title' => 'Domani', 'end_date' => '2026-09-17']),
        'this_week' => Task::factory()->forCreator($actor)->create(['title' => 'Fine settimana', 'end_date' => '2026-09-20']),
        'this_month_no_date' => Task::factory()->forCreator($actor)->create(['title' => 'Senza scadenza']),
        'this_month_boundary' => Task::factory()->forCreator($actor)->create(['title' => 'Fine mese', 'end_date' => '2026-09-30']),
        'later' => Task::factory()->forCreator($actor)->create(['title' => 'Più avanti', 'end_date' => '2026-10-01']),
        'completed' => Task::factory()->forCreator($actor)->inStatus($closedStatus)->create(['title' => 'Completato', 'end_date' => '2026-09-01']),
    ];
}

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-09-16 09:00:00'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('AC-002: by due, key=overdue returns only the task due before today', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'overdue']]))->toBe(['Scaduto']);
});

it('AC-002: by due, key=today returns only the task due today', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'today']]))->toBe(['Oggi']);
});

it('AC-002: by due, key=tomorrow returns only the task due tomorrow', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'tomorrow']]))->toBe(['Domani']);
});

it('AC-002: by due, key=this_week includes the ISO week Sunday boundary', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'this_week']]))->toBe(['Fine settimana']);
});

it('AC-002: by due, key=this_month includes both a task with no due date and the month-end boundary', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'this_month']]))
        ->toBe(['Fine mese', 'Senza scadenza']);
});

it('AC-002: by due, key=later returns only the task due after month end', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'later']]))->toBe(['Più avanti']);
});

it('AC-002: by due, key=completed always returns the closed task regardless of its date', function () {
    $actor = taskKanbanActor();
    taskKanbanDueFixture($actor);
    Sanctum::actingAs($actor);

    expect(kanbanRowTitles(['kanbanGroup' => ['by' => 'due', 'key' => 'completed']]))->toBe(['Completato']);
});

// --- AC-003: every 422 branch --------------------------------------------

it('AC-003: kanbanGroup on a domain without support is a 422', function () {
    Permission::findOrCreate('users.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.viewAny');
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25,
        'kanbanGroup' => ['by' => 'status', 'key' => 1],
    ])->assertStatus(422)->assertJsonValidationErrors('kanbanGroup');
});

it('AC-003: an unknown kanbanGroup.by is a 422', function () {
    $actor = taskKanbanActor();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'kanbanGroup' => ['by' => 'priority', 'key' => 1],
    ])->assertStatus(422)->assertJsonValidationErrors('kanbanGroup.by');
});

it('AC-003: an unknown due bucket key is a 422', function () {
    $actor = taskKanbanActor();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'kanbanGroup' => ['by' => 'due', 'key' => 'yesterday'],
    ])->assertStatus(422)->assertJsonValidationErrors('kanbanGroup.key');
});

it('AC-003: a non-existent status id is a 422', function () {
    $actor = taskKanbanActor();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'kanbanGroup' => ['by' => 'status', 'key' => 999999],
    ])->assertStatus(422)->assertJsonValidationErrors('kanbanGroup.key');
});

it('AC-003: kanbanGroup combined with tree is a 422', function () {
    $actor = taskKanbanActor();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25,
        'tree' => true,
        'kanbanGroup' => ['by' => 'due', 'key' => 'today'],
    ])->assertStatus(422)->assertJsonValidationErrors('kanbanGroup');
});
