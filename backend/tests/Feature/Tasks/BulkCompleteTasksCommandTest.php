<?php

use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `tasks:bulk-complete`: closes a list of tasks with a TOTAL time spent,
| split evenly, through the same all-or-nothing TaskBulkService of
| POST /api/tasks/bulk (spec 0156 D-6 + spec 0338).
|--------------------------------------------------------------------------
*/

/**
 * @return array{0: User, 1: array<int, Task>, 2: TaskType}
 */
function bulkCompleteCommandScenario(int $taskCount = 3): array
{
    $actor = taskCompletionActorWith(['complete']);
    $tasks = Task::factory()->count($taskCount)->forCreator($actor)->create()->all();
    $type = TaskType::factory()->create(['is_default' => true]);

    return [$actor, $tasks, $type];
}

/**
 * @param  array<int, Task>  $tasks
 */
function bulkCompleteIds(array $tasks): string
{
    return implode(',', array_map(fn (Task $task): int => $task->id, $tasks));
}

function closedPositiveStatusId(): int
{
    return TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail()->id;
}

it('closes every task and splits the total evenly, the remainder to the first ones', function () {
    [$actor, $tasks, $type] = bulkCompleteCommandScenario();

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks),
        'minutes' => 100,
        '--user' => $actor->email,
        '--date' => '2026-11-14',
        '--force' => true,
    ])->assertExitCode(Command::SUCCESS);

    foreach ($tasks as $task) {
        expect($task->fresh()->task_status_id)->toBe(closedPositiveStatusId());
    }

    $minutes = array_map(fn (Task $task): int => (int) TimeEntry::query()->where('task_id', $task->id)->sum('minutes'), $tasks);
    expect($minutes)->toBe([34, 33, 33])
        ->and(TimeEntry::query()->where('task_type_id', $type->id)->where('date', '2026-11-14')->count())->toBe(3);
});

it('gives each task the next consecutive slot when a start time is given', function () {
    [$actor, $tasks] = bulkCompleteCommandScenario(2);

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks),
        'minutes' => 90,
        '--user' => (string) $actor->id,
        '--start' => '09:00',
        '--force' => true,
    ])->assertExitCode(Command::SUCCESS);

    $slots = TimeEntry::query()->orderBy('start_time')->get()
        ->map(fn (TimeEntry $entry): string => substr((string) $entry->start_time, 0, 5).'-'.substr((string) $entry->end_time, 0, 5))
        ->all();

    expect($slots)->toBe(['09:00-09:45', '09:45-10:30']);
});

it('closes nothing when one task cannot be completed', function () {
    [$actor, $tasks] = bulkCompleteCommandScenario(2);
    $missingId = Task::query()->max('id') + 100;

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks).','.$missingId,
        'minutes' => 60,
        '--user' => $actor->email,
        '--force' => true,
    ])->expectsOutputToContain('No task closed')->assertExitCode(Command::FAILURE);

    foreach ($tasks as $task) {
        expect($task->fresh()->task_status_id)->toBe($task->task_status_id);
    }
    expect(TimeEntry::query()->count())->toBe(0);
});

it('refuses a user without the tasks.complete permission', function () {
    [, $tasks] = bulkCompleteCommandScenario(1);
    $outsider = taskCompletionActorWith([]);

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks),
        'minutes' => 30,
        '--user' => $outsider->email,
        '--force' => true,
    ])->expectsOutputToContain('tasks.complete')->assertExitCode(Command::FAILURE);

    expect(TimeEntry::query()->count())->toBe(0);
});

it('rejects invalid input before touching anything', function (array $arguments, string $message) {
    [$actor, $tasks] = bulkCompleteCommandScenario(2);

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks),
        'minutes' => 60,
        '--user' => $actor->email,
        '--force' => true,
        ...$arguments,
    ])->expectsOutputToContain($message)->assertExitCode(Command::FAILURE);

    expect(TimeEntry::query()->count())->toBe(0);
})->with([
    'no user' => [['--user' => null], 'The --user option is required'],
    'malformed id list' => [['ids' => '1,abc'], 'Invalid task id "abc"'],
    'fewer minutes than tasks' => [['minutes' => 1], 'at least 2'],
    'malformed date' => [['--date' => '14/11/2026'], 'date'],
    'past the end of the day' => [['--start' => '23:30'], '23:59'],
]);

it('asks for confirmation and changes nothing when declined', function () {
    [$actor, $tasks] = bulkCompleteCommandScenario(1);

    $this->artisan('tasks:bulk-complete', [
        'ids' => bulkCompleteIds($tasks),
        'minutes' => 30,
        '--user' => $actor->email,
    ])->expectsConfirmation('Close these tasks?', 'no')
        ->expectsOutputToContain('Nothing changed')
        ->assertExitCode(Command::FAILURE);

    expect(TimeEntry::query()->count())->toBe(0);
});
