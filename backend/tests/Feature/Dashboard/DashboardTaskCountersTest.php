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
| GET /api/dashboard/tasks (spec 0151, AC-001..005)
|--------------------------------------------------------------------------
|
| One shared fixture (dashboardFixture()) builds every task the AC-001/002/
| 003/005 cases read from, so the parity check in AC-005 is guaranteed to
| compare against the SAME rows the counters were computed on.
*/

function dashboardActor(): User
{
    Permission::findOrCreate('tasks.viewAny');

    $user = User::factory()->create();
    $user->givePermissionTo('tasks.viewAny');

    return $user;
}

function dashboardTaskInGroup(TaskStatusGroup $group, array $attributes = []): Task
{
    $status = TaskStatus::factory()->group($group)->create();

    return Task::factory()->inStatus($status)->create($attributes);
}

/**
 * @return array<string, mixed>
 */
function dashboardCounters(): array
{
    return test()->getJson('/api/dashboard/tasks')->assertOk()->json('data');
}

/**
 * @param  array<string, mixed>  $advancedFilters
 */
function dashboardRowCount(array $advancedFilters): int
{
    return count(test()->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 50, 'advancedFilters' => $advancedFilters,
    ])->assertOk()->json('items'));
}

/**
 * Builds the fixture behind every case below and returns the acting user.
 * D-3 exclusivity is baked into the attributes themselves: task 3 is both
 * requested AND assigned to me (assigned_to_me wins), task 5 is both
 * created AND requested by me (assigned_by_me wins, not created_by_me).
 */
function dashboardFixture(): User
{
    $me = dashboardActor();
    Sanctum::actingAs($me);

    // 1: assigned to me — counts in not_completed + assigned_to_me.
    dashboardTaskInGroup(TaskStatusGroup::Open, ['estimated_minutes' => 30])
        ->assignees()->attach($me->id);

    // 2: requested by me, not assigned — assigned_by_me.
    dashboardTaskInGroup(TaskStatusGroup::Open, ['requester_id' => $me->id, 'estimated_minutes' => 20]);

    // 3: requested AND assigned to me — assigned_to_me only (AC-002).
    dashboardTaskInGroup(TaskStatusGroup::Open, ['requester_id' => $me->id, 'estimated_minutes' => 10])
        ->assignees()->attach($me->id);

    // 4: created by me, requester someone else — created_by_me.
    dashboardTaskInGroup(TaskStatusGroup::Open, ['creator_id' => $me->id, 'estimated_minutes' => 15]);

    // 5: created AND requested by me — assigned_by_me, NOT created_by_me (AC-002).
    dashboardTaskInGroup(TaskStatusGroup::Open, ['creator_id' => $me->id, 'requester_id' => $me->id, 'estimated_minutes' => 5]);

    // 6: watched by me — observed_by_me.
    dashboardTaskInGroup(TaskStatusGroup::Open, ['estimated_minutes' => 25])
        ->watchers()->attach($me->id);

    // 8: requested by me, in_validation — to_validate (AC-003).
    dashboardTaskInGroup(TaskStatusGroup::InValidation, ['requester_id' => $me->id, 'estimated_minutes' => 8]);

    // 9: closed, otherwise every membership role is mine — excluded entirely.
    dashboardTaskInGroup(TaskStatusGroup::ClosedPositive, ['creator_id' => $me->id, 'requester_id' => $me->id, 'estimated_minutes' => 999])
        ->assignees()->attach($me->id);

    // 10: open but no membership at all — not visible, excluded.
    dashboardTaskInGroup(TaskStatusGroup::Open, ['estimated_minutes' => 999]);

    return $me;
}

it('computes every counter per D-3/D-4/D-5, closed and invisible tasks excluded (AC-001)', function () {
    dashboardFixture();

    $data = dashboardCounters();

    expect($data['not_completed'])->toBe(['count' => 7, 'total_minutes' => 113])
        ->and($data['assigned_to_me'])->toBe(['count' => 2, 'total_minutes' => 40])
        ->and($data['created_by_me'])->toBe(['count' => 1, 'total_minutes' => 15])
        ->and($data['observed_by_me'])->toBe(['count' => 1, 'total_minutes' => 25]);
});

it('keeps assigned_to_me/assigned_by_me and requester/creator exclusive (AC-002)', function () {
    dashboardFixture();

    $data = dashboardCounters();

    expect($data['assigned_by_me']['count'])->toBe(3)
        ->and($data['assigned_by_me']['total_minutes'])->toBe(33);
});

it('to_validate counts only assigned_by_me tasks in phase in_validation (AC-003)', function () {
    dashboardFixture();

    $data = dashboardCounters();

    expect($data['assigned_by_me']['to_validate'])->toBe(['count' => 1, 'total_minutes' => 8]);
});

it('returns 403 without tasks.viewAny (AC-004)', function () {
    Permission::findOrCreate('tasks.viewAny');
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/dashboard/tasks')->assertForbidden();
});

it('returns 401 unauthenticated (AC-004)', function () {
    $this->getJson('/api/dashboard/tasks')->assertUnauthorized();
});

it('every counter matches the tasks grid row count under the same filters (AC-005)', function () {
    dashboardFixture();

    $data = dashboardCounters();

    // spec 0153, D-1: `assignment` is now a multi-value filter — a single
    // scope still travels as a one-element array.
    expect($data['not_completed']['count'])->toBe(dashboardRowCount(['status' => 'open', 'assignment' => ['all']]))
        ->and($data['assigned_to_me']['count'])->toBe(dashboardRowCount(['status' => 'open', 'assignment' => ['assigned_to_me']]))
        ->and($data['assigned_by_me']['count'])->toBe(dashboardRowCount(['status' => 'open', 'assignment' => ['assigned_by_me']]))
        ->and($data['created_by_me']['count'])->toBe(dashboardRowCount(['status' => 'open', 'assignment' => ['created_by_me']]))
        ->and($data['observed_by_me']['count'])->toBe(dashboardRowCount(['status' => 'open', 'assignment' => ['observed_by_me']]))
        ->and($data['assigned_by_me']['to_validate']['count'])->toBe(dashboardRowCount(['status' => 'in_validation', 'assignment' => ['assigned_by_me']]));
});
