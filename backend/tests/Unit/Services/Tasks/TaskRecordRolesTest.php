<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskRecordRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| WHO the actor is on a Task record (spec 0116, D-1/D-2)
|--------------------------------------------------------------------------
|
| TaskRecordRoles is the sole place the four membership roles plus the
| "gestore" deroga (D-2) are decided. TaskAbilityResolverTest exercises the
| matrix built on top; this file exercises each role in isolation.
*/

uses(TestCase::class, RefreshDatabase::class);

it('AC-recordroles: isCreator is true only for the actor named on creator_id', function () {
    $creator = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create();

    expect(TaskRecordRoles::isCreator($creator, $task))->toBeTrue()
        ->and(TaskRecordRoles::isCreator($stranger, $task))->toBeFalse();
});

it('AC-recordroles: isRequester is true only for the actor named on requester_id, and false when it is null', function () {
    $requester = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->create(['requester_id' => $requester->id]);
    $taskWithoutRequester = Task::factory()->create(['requester_id' => null]);

    expect(TaskRecordRoles::isRequester($requester, $task))->toBeTrue()
        ->and(TaskRecordRoles::isRequester($stranger, $task))->toBeFalse()
        ->and(TaskRecordRoles::isRequester($requester, $taskWithoutRequester))->toBeFalse();
});

it('AC-recordroles: isAssignee reads the task_assignee pivot, loaded or not', function () {
    $assignee = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee);

    expect(TaskRecordRoles::isAssignee($assignee, $task))->toBeTrue()
        ->and(TaskRecordRoles::isAssignee($stranger, $task))->toBeFalse();

    $task->load('assignees');

    expect(TaskRecordRoles::isAssignee($assignee, $task))->toBeTrue()
        ->and(TaskRecordRoles::isAssignee($stranger, $task))->toBeFalse();
});

it('AC-recordroles: isWatcher reads the task_watcher pivot, loaded or not', function () {
    $watcher = User::factory()->create();
    $stranger = User::factory()->create();
    $task = Task::factory()->create();
    $task->watchers()->attach($watcher);

    expect(TaskRecordRoles::isWatcher($watcher, $task))->toBeTrue()
        ->and(TaskRecordRoles::isWatcher($stranger, $task))->toBeFalse();

    $task->load('watchers');

    expect(TaskRecordRoles::isWatcher($watcher, $task))->toBeTrue()
        ->and(TaskRecordRoles::isWatcher($stranger, $task))->toBeFalse();
});

// REQUIREMENT CHANGED (spec 0118 D-9): AC-083 of spec 0101 ("an assignee and
// a watcher may be the same person") is FORMALLY RETIRED — the write path
// (TaskService, guarded by TaskWatcherOverlapGuard) now refuses a
// `watcher_ids` id that is also an assignee. This is not a regression of
// that rule: TaskRecordRoles is a pure READ-side role resolver, unaware of
// any write-time constraint, so it still has to answer correctly for
// whatever a row actually holds — including a pivot shape the write path
// itself would now refuse to create (e.g. legacy data written before D-9).
it('AC-recordroles: isAssignee/isWatcher each read their own pivot independently, even on a row the write path could no longer produce (spec 0118 D-9)', function () {
    $both = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($both);
    $task->watchers()->attach($both);

    expect(TaskRecordRoles::isAssignee($both, $task))->toBeTrue()
        ->and(TaskRecordRoles::isWatcher($both, $task))->toBeTrue();
});

it('AC-008: isManager is true for an actor holding tasks.manageAll who is NOT an assignee of this task', function () {
    Permission::findOrCreate('tasks.manageAll');
    $manager = User::factory()->create();
    $manager->givePermissionTo('tasks.manageAll');
    $task = Task::factory()->create();

    expect(TaskRecordRoles::isManager($manager, $task))->toBeTrue();
});

it('AC-009: isManager decays to false when the manageAll holder is ALSO an assignee of this task (D-2 deroga)', function () {
    Permission::findOrCreate('tasks.manageAll');
    $managerAssignee = User::factory()->create();
    $managerAssignee->givePermissionTo('tasks.manageAll');
    $task = Task::factory()->create();
    $task->assignees()->attach($managerAssignee);

    expect(TaskRecordRoles::isManager($managerAssignee, $task))->toBeFalse();
});

it('isManager is false without the tasks.manageAll permission, whatever the record role', function () {
    Permission::findOrCreate('tasks.manageAll');
    $creator = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create();

    expect(TaskRecordRoles::isManager($creator, $task))->toBeFalse();
});
