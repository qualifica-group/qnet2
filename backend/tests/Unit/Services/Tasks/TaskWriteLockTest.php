<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Services\Tasks\TaskWriteLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| The structural write lock over a frozen Task (spec 0116, D-7, AC-031..AC-034)
|--------------------------------------------------------------------------
*/

uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('taskFrozenBy')) {
    function taskFrozenBy(TaskStatusGroup $group, bool $blocked = false): Task
    {
        $status = TaskStatus::factory()->group($group)->create();

        return Task::factory()->inStatus($status)->create(['is_blocked' => $blocked]);
    }
}

// ---------------------------------------------------------------------------
// isLocked
// ---------------------------------------------------------------------------

it('AC-034: is not locked on an open, unblocked task', function () {
    expect(TaskWriteLock::isLocked(taskFrozenBy(TaskStatusGroup::Open)))->toBeFalse();
});

it('is not locked on a pending, unblocked task', function () {
    expect(TaskWriteLock::isLocked(taskFrozenBy(TaskStatusGroup::Pending)))->toBeFalse();
});

it('AC-031: is locked when is_blocked is true, whatever the phase', function () {
    expect(TaskWriteLock::isLocked(taskFrozenBy(TaskStatusGroup::Open, blocked: true)))->toBeTrue();
});

it('AC-032/AC-033: is locked in in_validation, closed_positive and closed_negative alike', function (TaskStatusGroup $group) {
    expect(TaskWriteLock::isLocked(taskFrozenBy($group)))->toBeTrue();
})->with([
    'in_validation' => [TaskStatusGroup::InValidation],
    'closed_positive' => [TaskStatusGroup::ClosedPositive],
    'closed_negative' => [TaskStatusGroup::ClosedNegative],
]);

// ---------------------------------------------------------------------------
// assertStructuralWriteAllowed
// ---------------------------------------------------------------------------

it('AC-034: on an unlocked task, structural keys pass untouched', function () {
    $task = taskFrozenBy(TaskStatusGroup::Open);

    TaskWriteLock::assertStructuralWriteAllowed($task, ['title', 'end_date']);
})->throwsNoExceptions();

it('AC-031: on a locked task, the OPERATIVE keys pass untouched', function () {
    $task = taskFrozenBy(TaskStatusGroup::Open, blocked: true);

    TaskWriteLock::assertStructuralWriteAllowed($task, ['task_status_id', 'closure_feedback']);
})->throwsNoExceptions();

it('AC-031: on a locked task, a single structural key throws with a message keyed to that field', function () {
    $task = taskFrozenBy(TaskStatusGroup::Open, blocked: true);

    try {
        TaskWriteLock::assertStructuralWriteAllowed($task, ['title']);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('title')
            ->and($exception->errors())->not->toHaveKey('task_status_id');
    }
});

it('AC-032: a mixed submission throws once PER structural key and leaves the operative ones out of the errors', function () {
    $task = taskFrozenBy(TaskStatusGroup::ClosedPositive);

    try {
        TaskWriteLock::assertStructuralWriteAllowed($task, ['title', 'closure_feedback', 'end_date']);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKeys(['title', 'end_date'])
            ->and($exception->errors())->not->toHaveKey('closure_feedback');
    }
});

it('AC-033: closed_negative freezes structural writes exactly like closed_positive', function () {
    $task = taskFrozenBy(TaskStatusGroup::ClosedNegative);

    try {
        TaskWriteLock::assertStructuralWriteAllowed($task, ['title']);
        $this->fail('Expected a ValidationException.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('title');
    }
});

// ---------------------------------------------------------------------------
// assertDeletable
// ---------------------------------------------------------------------------

it('AC-034: an unlocked task is deletable', function () {
    TaskWriteLock::assertDeletable(taskFrozenBy(TaskStatusGroup::Open));
})->throwsNoExceptions();

it('AC-033: a task in_validation is not deletable, 409', function () {
    try {
        TaskWriteLock::assertDeletable(taskFrozenBy(TaskStatusGroup::InValidation));
        $this->fail('Expected an HttpException.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

it('a blocked task is not deletable, 409', function () {
    try {
        TaskWriteLock::assertDeletable(taskFrozenBy(TaskStatusGroup::Open, blocked: true));
        $this->fail('Expected an HttpException.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }
});

// ---------------------------------------------------------------------------
// OPERATIVE_KEYS — the constant TaskService reads to compute the diff
// ---------------------------------------------------------------------------

it('D-7: OPERATIVE_KEYS is exactly task_status_id and closure_feedback', function () {
    expect(TaskWriteLock::OPERATIVE_KEYS)->toEqualCanonicalizing(['task_status_id', 'closure_feedback']);
});
