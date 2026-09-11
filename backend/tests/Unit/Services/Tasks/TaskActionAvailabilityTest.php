<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\Tasks\TaskActionAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| WHEN a domain action makes sense (spec 0116, D-1), by PHASE alone
|--------------------------------------------------------------------------
|
| Modelled on ContractActionAvailabilityTest: only the task's status GROUP
| changes across the cases, never a label, never `tasks.*` permissions —
| this class answers availability, not authorization.
*/

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('taskInGroup')) {
    function taskInGroup(TaskStatusGroup $group, bool $blocked = false): Task
    {
        $status = TaskStatus::factory()->group($group)->create();

        return Task::factory()->inStatus($status)->create(['is_blocked' => $blocked]);
    }
}

it('is completable in open, pending, closed_negative but not in_validation or closed_positive', function (TaskStatusGroup $group, bool $completable) {
    $availability = new TaskActionAvailability;

    expect($availability->isCompletable(taskInGroup($group)))->toBe($completable)
        ->and($availability->isUncompletable(taskInGroup($group)))->toBe(! $completable);
})->with([
    'open' => [TaskStatusGroup::Open, true],
    'pending' => [TaskStatusGroup::Pending, true],
    'in_validation' => [TaskStatusGroup::InValidation, false],
    'closed_positive' => [TaskStatusGroup::ClosedPositive, false],
    'closed_negative' => [TaskStatusGroup::ClosedNegative, false],
]);

it('AC-027: is validatable only in the in_validation phase', function (TaskStatusGroup $group, bool $validatable) {
    $availability = new TaskActionAvailability;

    expect($availability->isValidatable(taskInGroup($group)))->toBe($validatable);
})->with([
    'open' => [TaskStatusGroup::Open, false],
    'pending' => [TaskStatusGroup::Pending, false],
    'in_validation' => [TaskStatusGroup::InValidation, true],
    'closed_positive' => [TaskStatusGroup::ClosedPositive, false],
    'closed_negative' => [TaskStatusGroup::ClosedNegative, false],
]);

it('AC-028: is blockable only while not already blocked, and unblockable only while blocked', function () {
    $availability = new TaskActionAvailability;
    $unblocked = taskInGroup(TaskStatusGroup::Open, blocked: false);
    $blocked = taskInGroup(TaskStatusGroup::Open, blocked: true);

    expect($availability->isBlockable($unblocked))->toBeTrue()
        ->and($availability->isUnblockable($unblocked))->toBeFalse()
        ->and($availability->isBlockable($blocked))->toBeFalse()
        ->and($availability->isUnblockable($blocked))->toBeTrue();
});

it('is_blocked does not change completability or validatability by itself, that veto lives in the Service (D-8)', function () {
    $availability = new TaskActionAvailability;
    $blockedButOpen = taskInGroup(TaskStatusGroup::Open, blocked: true);

    expect($availability->isCompletable($blockedButOpen))->toBeTrue()
        ->and($availability->isValidatable($blockedButOpen))->toBeFalse();
});
