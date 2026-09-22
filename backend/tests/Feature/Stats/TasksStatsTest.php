<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| GET /api/stats/tasks (spec 0147, AC-003)
|--------------------------------------------------------------------------
|
| The work-order Task board's KPIs over the actor's visible ROOT tasks.
| "Today" is pinned to 2026-09-23.
*/

function tasksStatsActor(bool $viewAll = true): User
{
    foreach (['viewAny', 'viewAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }

    $user = User::factory()->create();
    $user->givePermissionTo('tasks.viewAny');

    if ($viewAll) {
        $user->givePermissionTo('tasks.viewAll');
    }

    return $user;
}

/**
 * @return array<string, array<string, mixed>>
 */
function tasksStatsWidgets(): array
{
    return collect(test()->getJson('/api/stats/tasks')->assertOk()->json('data.widgets'))->keyBy('key')->all();
}

beforeEach(function () {
    Carbon::setTestNow('2026-09-23 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('computes overdue, due today, estimated and actual minutes on root tasks only (AC-003)', function () {
    Sanctum::actingAs(tasksStatsActor());
    $open = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $overdue = Task::factory()->inStatus($open)->create(['start_date' => null, 'end_date' => '2026-09-20', 'estimated_minutes' => 60]);
    Task::factory()->inStatus($closed)->create(['start_date' => null, 'end_date' => '2026-09-20', 'estimated_minutes' => 30]);
    Task::factory()->inStatus($open)->create(['start_date' => '2026-09-23', 'end_date' => null, 'estimated_minutes' => null]);
    $subtask = Task::factory()->inStatus($open)->childOf($overdue)->create(['start_date' => null, 'end_date' => '2026-09-20', 'estimated_minutes' => 500]);

    TimeEntry::factory()->create(['task_id' => $overdue->id, 'minutes' => 45]);
    TimeEntry::factory()->create(['task_id' => $subtask->id, 'minutes' => 120]);

    $widgets = tasksStatsWidgets();

    expect($widgets['overdue']['value'])->toBe(1)
        ->and($widgets['due_today']['value'])->toBe(1)
        ->and($widgets['estimated_minutes']['value'])->toBe(90)
        ->and($widgets['estimated_minutes']['format'])->toBe('duration')
        ->and($widgets['actual_minutes']['value'])->toBe(45)
        ->and($widgets['actual_minutes']['format'])->toBe('duration');
});

it('breaks root tasks down by status and priority (AC-003)', function () {
    Sanctum::actingAs(tasksStatsActor());
    $status = TaskStatus::factory()->create(['name' => 'Stato statistiche', 'color' => 'blue']);
    $priority = TaskPriority::factory()->create(['name' => 'Priorita statistiche', 'color' => 'red']);
    $root = Task::factory()->inStatus($status)->create(['task_priority_id' => $priority->id]);
    Task::factory()->inStatus($status)->create(['task_priority_id' => $priority->id]);
    Task::factory()->inStatus($status)->childOf($root)->create(['task_priority_id' => $priority->id]);

    $widgets = tasksStatsWidgets();

    expect($widgets['by_status']['total'])->toBe(2)
        ->and($widgets['by_status']['items'])->toBe([['key' => (string) $status->id, 'label' => 'Stato statistiche', 'value' => 2, 'color' => 'blue']])
        ->and($widgets['by_priority']['items'])->toBe([['key' => (string) $priority->id, 'label' => 'Priorita statistiche', 'value' => 2, 'color' => 'red']]);
});

it('counts only the tasks the actor may see without tasks.viewAll (AC-003)', function () {
    $actor = tasksStatsActor(viewAll: false);
    Sanctum::actingAs($actor);
    Task::factory()->create(['requester_id' => $actor->id, 'start_date' => '2026-09-23', 'end_date' => null]);
    Task::factory()->create(['start_date' => '2026-09-23', 'end_date' => null]);

    $widgets = tasksStatsWidgets();

    expect($widgets['due_today']['value'])->toBe(1)
        ->and($widgets['by_status']['total'])->toBe(1)
        ->and(array_sum(array_column($widgets['trend']['points'], 'value')))->toBe(1);
});

it('returns 403 without tasks.viewAny (AC-003)', function () {
    Permission::findOrCreate('tasks.viewAny');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/stats/tasks')->assertForbidden();
});
