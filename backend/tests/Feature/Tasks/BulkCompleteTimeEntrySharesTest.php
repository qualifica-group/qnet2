<?php

use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Bulk complete splits the TOTAL time entry across tasks (spec 0338)
|--------------------------------------------------------------------------
| Both endpoints share the rules and the splitter: POST /api/tasks/bulk and
| POST /api/work-orders/{workOrder}/task-board/bulk.
*/

/**
 * @return array{0: User, 1: WorkOrder, 2: array<int, Task>}
 */
function sharesScenario(int $taskCount = 3): array
{
    $actor = taskCompletionActorWith(['complete']);

    foreach (['view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

    $workOrder = WorkOrder::factory()->create();
    $tasks = Task::factory()->count($taskCount)->forCreator($actor)->create(['work_order_id' => $workOrder->id])->all();

    Sanctum::actingAs($actor);

    return [$actor, $workOrder, $tasks];
}

/**
 * @param  array<int, Task>  $tasks
 * @param  array<int, int>  $minutes
 * @return array<int, array{task_id: int, minutes: int}>
 */
function sharesFor(array $tasks, array $minutes): array
{
    return collect($tasks)->values()->map(fn (Task $task, int $i) => ['task_id' => $task->id, 'minutes' => $minutes[$i]])->all();
}

/**
 * @param  array<string, mixed>  $payload
 */
function postBulkComplete(string $endpoint, ?WorkOrder $workOrder, array $payload): TestResponse
{
    $url = $endpoint === 'tasks' ? '/api/tasks/bulk' : "/api/work-orders/{$workOrder->id}/task-board/bulk";

    return test()->postJson($url, ['action' => 'complete', ...$payload]);
}

function timeOf(?string $value): ?string
{
    return $value === null ? null : substr($value, 0, 5);
}

dataset('bulkEndpoints', ['tasks module' => ['tasks'], 'work order board' => ['board']]);

it('AC-001: the total is split, each task gets its own share', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario();

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 180]),
        'time_entry_shares' => sharesFor($tasks, [60, 60, 60]),
    ])->assertOk();

    foreach ($tasks as $task) {
        expect(TimeEntry::query()->where('task_id', $task->id)->sum('minutes'))->toBe(60);
    }
    expect(TimeEntry::query()->sum('minutes'))->toBe(180);
})->with('bulkEndpoints');

it('AC-002: with a start time the slots are consecutive in the order of the shares', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario();

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 180, 'start_time' => '09:00', 'end_time' => '12:00']),
        'time_entry_shares' => sharesFor($tasks, [90, 45, 45]),
    ])->assertOk();

    $slots = collect($tasks)->map(function (Task $task) {
        $entry = TimeEntry::query()->where('task_id', $task->id)->sole();

        return timeOf($entry->start_time).'-'.timeOf($entry->end_time);
    })->all();

    expect($slots)->toBe(['09:00-10:30', '10:30-11:15', '11:15-12:00']);
})->with('bulkEndpoints');

it('AC-002: without a start time the time entries carry no hours', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario(2);

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 50]),
        'time_entry_shares' => sharesFor($tasks, [30, 20]),
    ])->assertOk();

    expect(TimeEntry::query()->whereNotNull('start_time')->orWhereNotNull('end_time')->count())->toBe(0)
        ->and(TimeEntry::query()->count())->toBe(2);
})->with('bulkEndpoints');

/**
 * @param  array<string, mixed>  $p  a valid payload for three tasks, 30 minutes each
 * @return array<string, mixed>
 */
function brokenSharesPayload(string $case, array $p): array
{
    $shares = $p['time_entry_shares'];
    $ids = $p['task_ids'];

    return match ($case) {
        'sum differs' => [...$p, 'time_entry_shares' => [$shares[0], $shares[1], ['task_id' => $ids[2], 'minutes' => 29]]],
        'zero share' => [...$p, 'time_entry_shares' => [['task_id' => $ids[0], 'minutes' => 0], ['task_id' => $ids[1], 'minutes' => 60], $shares[2]]],
        'missing task' => [...$p, 'time_entry_shares' => array_slice($shares, 0, 2)],
        'foreign task' => [...$p, 'time_entry_shares' => [$shares[0], $shares[1], ['task_id' => 999999, 'minutes' => 30]]],
        'duplicate task' => [...$p, 'time_entry_shares' => [$shares[0], $shares[1], $shares[1]]],
        'shares without time entry' => array_diff_key($p, ['time_entry' => 1]),
        'time entry without shares' => array_diff_key($p, ['time_entry_shares' => 1]),
    };
}

it('AC-003: invalid shares are 422 on the right field and nothing is written', function (string $case, string $errorKey, string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario();
    $payload = [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 90]),
        'time_entry_shares' => sharesFor($tasks, [30, 30, 30]),
    ];

    // The board requires time_entry itself on complete, so that is the field it flags.
    $expectedKey = $case === 'shares without time entry' && $endpoint === 'board' ? 'time_entry' : $errorKey;

    postBulkComplete($endpoint, $workOrder, brokenSharesPayload($case, $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$expectedKey]);

    expect(TimeEntry::query()->count())->toBe(0);
    foreach ($tasks as $task) {
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);
    }
})->with([
    'sum differs' => ['sum differs', 'time_entry_shares'],
    'zero share' => ['zero share', 'time_entry_shares.0.minutes'],
    'missing task' => ['missing task', 'time_entry_shares'],
    'foreign task' => ['foreign task', 'time_entry_shares.2.task_id'],
    'duplicate task' => ['duplicate task', 'time_entry_shares.2.task_id'],
    'shares without time entry' => ['shares without time entry', 'time_entry_shares'],
    'time entry without shares' => ['time entry without shares', 'time_entry_shares'],
])->with(['tasks', 'board']);

it('AC-003: shares on an action other than complete are 422', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario(1);
    $url = $endpoint === 'tasks' ? '/api/tasks/bulk' : "/api/work-orders/{$workOrder->id}/task-board/bulk";

    $this->postJson($url, [
        'action' => 'block',
        'task_ids' => [$tasks[0]->id],
        'time_entry_shares' => sharesFor($tasks, [10]),
    ])->assertUnprocessable()->assertJsonValidationErrors(['time_entry_shares']);
})->with(['tasks', 'board']);

it('AC-004: a total that runs past midnight from the start time is 422 on time_entry.minutes', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario(2);

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 120, 'start_time' => '23:00', 'end_time' => '23:30']),
        'time_entry_shares' => sharesFor($tasks, [60, 60]),
    ])->assertUnprocessable()->assertJsonValidationErrors(['time_entry.minutes']);

    expect(TimeEntry::query()->count())->toBe(0);
})->with(['tasks', 'board']);

it('AC-004: the last slot may end at 23:59 but not at 24:00, the edit form only reads H:i', function (string $endpoint) {
    [, $workOrder, $tasks] = sharesScenario(2);
    $taskIds = array_map(fn (Task $t) => $t->id, $tasks);

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => $taskIds,
        'time_entry' => validTimeEntryPayload(['minutes' => 120, 'start_time' => '22:00', 'end_time' => '23:59']),
        'time_entry_shares' => sharesFor($tasks, [60, 60]),
    ])->assertUnprocessable()->assertJsonValidationErrors(['time_entry.minutes']);

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => $taskIds,
        'time_entry' => validTimeEntryPayload(['minutes' => 119, 'start_time' => '22:00', 'end_time' => '23:59']),
        'time_entry_shares' => sharesFor($tasks, [60, 59]),
    ])->assertOk();

    expect(TimeEntry::query()->orderBy('start_time')->pluck('end_time')->map(fn ($time) => substr((string) $time, 0, 5))->all())
        ->toBe(['23:00', '23:59']);
})->with(['tasks', 'board']);

it('AC-005: for_all_assignees logs the task share for each assignee, or for the closer without assignees', function (string $endpoint) {
    [$actor, $workOrder, $tasks] = sharesScenario(2);
    $other = User::factory()->create();
    $tasks[0]->assignees()->attach([$actor->id, $other->id]);

    postBulkComplete($endpoint, $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 40, 'start_time' => '09:00', 'end_time' => '09:40']),
        'time_entry_shares' => sharesFor($tasks, [30, 10]),
        'for_all_assignees' => true,
    ])->assertOk();

    $first = TimeEntry::query()->where('task_id', $tasks[0]->id)->get();
    expect($first)->toHaveCount(2)
        ->and($first->pluck('minutes')->unique()->all())->toBe([30])
        ->and($first->map(fn ($e) => timeOf($e->start_time).'-'.timeOf($e->end_time))->unique()->all())->toBe(['09:00-09:30'])
        ->and($first->pluck('user_id')->sort()->values()->all())->toBe(collect([$actor->id, $other->id])->sort()->values()->all());

    $second = TimeEntry::query()->where('task_id', $tasks[1]->id)->sole();
    expect($second->user_id)->toBe($actor->id)
        ->and($second->minutes)->toBe(10);
})->with(['tasks', 'board']);

it('AC-006: the board complete is all-or-nothing, a blocked task leaves everything untouched', function () {
    Notification::fake();
    [, $workOrder, $tasks] = sharesScenario();
    $tasks[1]->forceFill(['is_blocked' => true])->save();

    $response = postBulkComplete('board', $workOrder, [
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
        'time_entry' => validTimeEntryPayload(['minutes' => 90]),
        'time_entry_shares' => sharesFor($tasks, [30, 30, 30]),
    ])->assertUnprocessable()->assertJsonPath('success', false);

    expect($response->json('incompatible_tasks'))->toHaveCount(1)
        ->and($response->json('incompatible_tasks.0.id'))->toBe($tasks[1]->id)
        ->and($response->json('incompatible_tasks.0.reason'))->not->toBeEmpty()
        ->and($response->json('errors.task_ids'))->not->toBeEmpty()
        ->and(TimeEntry::query()->count())->toBe(0)
        ->and($workOrder->progressEntries()->count())->toBe(0);

    foreach ($tasks as $task) {
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);
    }
    Notification::assertNothingSent();
});

it('AC-007: the other board actions stay best-effort with a results payload', function () {
    [, $workOrder, $tasks] = sharesScenario(2);

    $response = test()->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block',
        'task_ids' => array_map(fn (Task $t) => $t->id, $tasks),
    ]);

    expect($response->json('data'))->toHaveKeys(['results', 'succeeded', 'failed']);
});

it('AC-008: the task module completes optional-time-entry tasks without time_entry nor shares', function () {
    $actor = taskCompletionActorWith(['complete']);
    $type = TaskType::factory()->optionalTimeEntry()->create();
    $tasks = Task::factory()->count(2)->forCreator($actor)->create(['task_type_id' => $type->id]);
    Sanctum::actingAs($actor);

    test()->postJson('/api/tasks/bulk', ['action' => 'complete', 'task_ids' => $tasks->pluck('id')->all()])
        ->assertOk()
        ->assertJsonPath('data.affected', 2);

    expect(TimeEntry::query()->count())->toBe(0);
});
