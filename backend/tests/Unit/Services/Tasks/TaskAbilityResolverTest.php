<?php

use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskAbilityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| THE role -> action matrix (spec 0116, D-1/D-3/D-5, AC-001..AC-012)
|--------------------------------------------------------------------------
|
| Rows (creator/requester, assignee, watcher, manager):
|   canUpdate:                SI / SI / NO / SI
|   canUpdateProtectedFields: SI / NO / NO / SI
|   canDelete:                SI / NO / NO / SI
|   canComplete:              SI / SI / NO / SI
|   canValidate:              SI / NO / NO / SI
|   canBlock:                 SI / NO / NO / SI
|
| Every actor here holds only the record role under test — no `tasks.*`
| resource permission is asserted here, TaskAbilityResolver never reads one
| except through TaskRecordRoles::isManager()'s `tasks.manageAll`.
*/

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('grantManageAll')) {
    function grantManageAll(User $user): void
    {
        Permission::findOrCreate('tasks.manageAll');
        $user->givePermissionTo('tasks.manageAll');
    }
}

// ---------------------------------------------------------------------------
// PROTECTED_FIELDS — the 18 fields of D-5 (spec 0121 adds requires_validation)
// ---------------------------------------------------------------------------

it('AC-015 (spec 0121): PROTECTED_FIELDS is exactly the 18 mandate fields, including requires_validation', function () {
    expect(TaskAbilityResolver::PROTECTED_FIELDS)->toEqualCanonicalizing([
        'title', 'registry_id', 'referent_id', 'parent_task_id', 'task_type_id',
        'task_priority_id', 'task_importance_id', 'task_category_id', 'opportunity_id',
        'work_order_id', 'requester_id', 'start_date', 'end_date', 'estimated_minutes',
        'requires_closure_feedback', 'requires_validation', 'assignee_ids', 'watcher_ids',
    ])->and(TaskAbilityResolver::PROTECTED_FIELDS)->toHaveCount(18)
        ->and(TaskAbilityResolver::PROTECTED_FIELDS)
        ->not->toContain('description', 'task_status_id', 'completion_date', 'start_time', 'end_time', 'closure_feedback');
});

// ---------------------------------------------------------------------------
// Creator / requester row — SI across the board
// ---------------------------------------------------------------------------

it('AC-004/AC-007: the creator may update, update protected fields, delete, complete, validate and block', function () {
    $creator = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create();

    expect(TaskAbilityResolver::canUpdate($creator, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($creator, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canDelete($creator, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canComplete($creator, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canValidate($creator, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canBlock($creator, $task))->toBeTrue();
});

it('AC-005: the requester, who is not the creator, sits on the same column as the creator', function () {
    $requester = User::factory()->create();
    $task = Task::factory()->create(['requester_id' => $requester->id]);

    expect(TaskAbilityResolver::canUpdate($requester, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($requester, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canDelete($requester, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canValidate($requester, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canBlock($requester, $task))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Assignee row — free fields SI, protected/delete/validate/block NO
// ---------------------------------------------------------------------------

it('AC-002/AC-003/AC-007: an assignee may update and complete but not touch protected fields, delete, validate or block', function () {
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee);

    expect(TaskAbilityResolver::canUpdate($assignee, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canComplete($assignee, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($assignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canDelete($assignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canValidate($assignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canBlock($assignee, $task))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Watcher row — NO across the board
// ---------------------------------------------------------------------------

it('AC-001: a pure watcher may not update, complete, delete, validate or block', function () {
    $watcher = User::factory()->create();
    $task = Task::factory()->create();
    $task->watchers()->attach($watcher);

    expect(TaskAbilityResolver::canUpdate($watcher, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($watcher, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canDelete($watcher, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canComplete($watcher, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canValidate($watcher, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canBlock($watcher, $task))->toBeFalse();
});

// ---------------------------------------------------------------------------
// Manager row (D-2) — SI across the board, unless also an assignee
// ---------------------------------------------------------------------------

it('AC-008: a manageAll holder unrelated to the task sits on the same column as the creator', function () {
    $manager = User::factory()->create();
    grantManageAll($manager);
    $task = Task::factory()->create();

    expect(TaskAbilityResolver::canUpdate($manager, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($manager, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canDelete($manager, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canValidate($manager, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canBlock($manager, $task))->toBeTrue();
});

it('AC-009/AC-010: a manageAll holder who is ALSO an assignee of this task decays to the assignee row', function () {
    $managerAssignee = User::factory()->create();
    grantManageAll($managerAssignee);
    $task = Task::factory()->create();
    $task->assignees()->attach($managerAssignee);

    expect(TaskAbilityResolver::canUpdate($managerAssignee, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canUpdateProtectedFields($managerAssignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canDelete($managerAssignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canValidate($managerAssignee, $task))->toBeFalse()
        ->and(TaskAbilityResolver::canBlock($managerAssignee, $task))->toBeFalse();
});

// ---------------------------------------------------------------------------
// D-3 — roles sum, the most permissive wins
// ---------------------------------------------------------------------------

it('AC-012: an actor who is creator AND watcher of the same task keeps the creator column', function () {
    $creatorWatcher = User::factory()->create();
    $task = Task::factory()->forCreator($creatorWatcher)->create();
    $task->watchers()->attach($creatorWatcher);

    expect(TaskAbilityResolver::canUpdateProtectedFields($creatorWatcher, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canDelete($creatorWatcher, $task))->toBeTrue();
});

it('D-3: an actor who is requester AND watcher (not creator) still keeps the requester column', function () {
    $requesterWatcher = User::factory()->create();
    $task = Task::factory()->create(['requester_id' => $requesterWatcher->id]);
    $task->watchers()->attach($requesterWatcher);

    expect(TaskAbilityResolver::canUpdateProtectedFields($requesterWatcher, $task))->toBeTrue()
        ->and(TaskAbilityResolver::canValidate($requesterWatcher, $task))->toBeTrue();
});

// ---------------------------------------------------------------------------
// completionRequiresValidation() — the percorso derivato (spec 0121, D-2)
// ---------------------------------------------------------------------------

it('AC-004/AC-008: a plain assignee on a flagged Task requires validation, but not on an unflagged one', function () {
    $assignee = User::factory()->create();
    $flagged = Task::factory()->requiringValidation()->create();
    $flagged->assignees()->attach($assignee);
    $unflagged = Task::factory()->create();
    $unflagged->assignees()->attach($assignee);

    expect(TaskAbilityResolver::completionRequiresValidation($assignee, $flagged))->toBeTrue()
        ->and(TaskAbilityResolver::completionRequiresValidation($assignee, $unflagged))->toBeFalse();
});

it('AC-006: the creator, the requester and a manager (not assignee) never require validation, flagged or not', function () {
    $creator = User::factory()->create();
    $requester = User::factory()->create();
    $manager = User::factory()->create();
    grantManageAll($manager);

    $creatorTask = Task::factory()->requiringValidation()->forCreator($creator)->create();
    $requesterTask = Task::factory()->requiringValidation()->create(['requester_id' => $requester->id]);
    $managerTask = Task::factory()->requiringValidation()->create();

    expect(TaskAbilityResolver::completionRequiresValidation($creator, $creatorTask))->toBeFalse()
        ->and(TaskAbilityResolver::completionRequiresValidation($requester, $requesterTask))->toBeFalse()
        ->and(TaskAbilityResolver::completionRequiresValidation($manager, $managerTask))->toBeFalse();
});

it('AC-007: a manageAll holder who is ALSO an assignee of the flagged Task requires validation (D-2 deroga)', function () {
    $managerAssignee = User::factory()->create();
    grantManageAll($managerAssignee);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($managerAssignee);

    expect(TaskAbilityResolver::completionRequiresValidation($managerAssignee, $task))->toBeTrue();
});

it('an assignee who is also creator or requester of the flagged Task does not require validation', function () {
    $creatorAssignee = User::factory()->create();
    $creatorAssigneeTask = Task::factory()->requiringValidation()->forCreator($creatorAssignee)->create();
    $creatorAssigneeTask->assignees()->attach($creatorAssignee);

    $requesterAssignee = User::factory()->create();
    $requesterAssigneeTask = Task::factory()->requiringValidation()->create(['requester_id' => $requesterAssignee->id]);
    $requesterAssigneeTask->assignees()->attach($requesterAssignee);

    expect(TaskAbilityResolver::completionRequiresValidation($creatorAssignee, $creatorAssigneeTask))->toBeFalse()
        ->and(TaskAbilityResolver::completionRequiresValidation($requesterAssignee, $requesterAssigneeTask))->toBeFalse();
});
