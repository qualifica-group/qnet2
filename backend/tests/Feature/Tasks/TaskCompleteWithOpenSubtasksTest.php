<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Completing a parent closes its open sub-tasks with it (spec 0418)
|--------------------------------------------------------------------------
| The time entry is a TOTAL split by `time_entry_shares` between the parent
| and its open sub-tasks; all-or-nothing, `incompatible_tasks` on refusal.
*/

function cascadeActor(): User
{
    $actor = taskCompletionActorWith(['view', 'complete']);
    Sanctum::actingAs($actor);

    return $actor;
}

function cascadeClosedPositiveId(): int
{
    return (int) TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->value('id');
}

/**
 * @param  array<string, mixed>  $payload
 */
function postCascadeComplete(Task $task, array $payload): TestResponse
{
    return test()->postJson("/api/tasks/{$task->id}/complete", $payload);
}

/**
 * @param  array<int, Task>  $tasks
 * @param  array<int, int>  $minutes
 * @return array<int, array{task_id: int, minutes: int}>
 */
function cascadeShares(array $tasks, array $minutes): array
{
    return array_map(fn (Task $task, int $m) => ['task_id' => $task->id, 'minutes' => $m], $tasks, $minutes);
}

it('AC-001: closes the parent and its open children, one time entry per share with consecutive slots', function () {
    $actor = cascadeActor();
    $parent = Task::factory()->forCreator($actor)->create();
    $first = Task::factory()->forCreator($actor)->childOf($parent)->create();
    $second = Task::factory()->forCreator($actor)->childOf($parent)->create();

    postCascadeComplete($parent, [
        'time_entry' => validTimeEntryPayload(['minutes' => 90, 'start_time' => '09:00', 'end_time' => '10:30']),
        'time_entry_shares' => cascadeShares([$parent, $first, $second], [30, 40, 20]),
    ])->assertOk()->assertJsonPath('data.open_subtasks_count', 0);

    foreach ([$parent, $first, $second] as $task) {
        expect($task->fresh()->task_status_id)->toBe(cascadeClosedPositiveId());
    }

    $slots = fn (Task $task) => TimeEntry::where('task_id', $task->id)->get()
        ->map(fn (TimeEntry $entry) => [$entry->minutes, substr((string) $entry->start_time, 0, 5), substr((string) $entry->end_time, 0, 5)])
        ->all();

    expect($slots($parent))->toBe([[30, '09:00', '09:30']])
        ->and($slots($first))->toBe([[40, '09:30', '10:10']])
        ->and($slots($second))->toBe([[20, '10:10', '10:30']]);
});

it('AC-002: walks open children only, grandchildren included; a closed child keeps its own sub-tree', function () {
    $actor = cascadeActor();
    $closed = TaskStatus::query()->findOrFail(cascadeClosedPositiveId());
    $parent = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    $grandchild = Task::factory()->forCreator($actor)->childOf($child)->create();
    $closedChild = Task::factory()->forCreator($actor)->inStatus($closed)->childOf($parent)->create();
    $untouched = Task::factory()->forCreator($actor)->childOf($closedChild)->create();
    $untouchedStatus = $untouched->task_status_id;

    $this->getJson("/api/tasks/{$parent->id}/open-subtasks")
        ->assertOk()
        ->assertJsonPath('data.0.id', $child->id)
        ->assertJsonPath('data.0.depth', 1)
        ->assertJsonPath('data.1.id', $grandchild->id)
        ->assertJsonPath('data.1.depth', 2)
        ->assertJsonCount(2, 'data');

    postCascadeComplete($parent, [
        'time_entry' => validTimeEntryPayload(['minutes' => 3]),
        'time_entry_shares' => cascadeShares([$parent, $child, $grandchild], [1, 1, 1]),
    ])->assertOk();

    expect($grandchild->fresh()->task_status_id)->toBe(cascadeClosedPositiveId())
        ->and($child->fresh()->task_status_id)->toBe(cascadeClosedPositiveId())
        ->and($parent->fresh()->task_status_id)->toBe(cascadeClosedPositiveId())
        ->and($untouched->fresh()->task_status_id)->toBe($untouchedStatus);
});

it('AC-003: shares that miss an open sub-task, or do not add up, are 422 time_entry_shares and nothing changes', function (string $case) {
    $actor = cascadeActor();
    $parent = Task::factory()->forCreator($actor)->create();
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create();
    $parentStatus = $parent->task_status_id;

    $shares = $case === 'missing'
        ? cascadeShares([$parent], [60])
        : cascadeShares([$parent, $child], [30, 20]);

    postCascadeComplete($parent, [
        'time_entry' => validTimeEntryPayload(['minutes' => 60]),
        'time_entry_shares' => $shares,
    ])->assertStatus(422)->assertJsonValidationErrors(['time_entry_shares']);

    expect($parent->fresh()->task_status_id)->toBe($parentStatus)
        ->and(TimeEntry::count())->toBe(0);
})->with(['missing' => ['missing'], 'mismatch' => ['mismatch']]);

it('AC-004: a child the actor cannot close makes the whole write incompatible', function (string $case) {
    $actor = cascadeActor();
    $parent = Task::factory()->forCreator($actor)->create();
    $refused = match ($case) {
        'not completable by the actor' => Task::factory()->childOf($parent)->create(),
        'would go to validation' => Task::factory()->requiringValidation()->childOf($parent)->create(['creator_id' => User::factory()->create()->id]),
        'in validation' => Task::factory()
            ->inStatus(TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create())
            ->forCreator($actor)->childOf($parent)->create(),
    };
    if ($case === 'would go to validation') {
        $refused->assignees()->attach($actor->id);
    }
    $ok = Task::factory()->forCreator($actor)->childOf($parent)->create();
    $okStatus = $ok->task_status_id;

    postCascadeComplete($parent, [
        'time_entry' => validTimeEntryPayload(['minutes' => 3]),
        'time_entry_shares' => cascadeShares([$parent, $refused, $ok], [1, 1, 1]),
    ])->assertStatus(422)
        ->assertJsonPath('incompatible_tasks.0.id', $refused->id);

    expect($ok->fresh()->task_status_id)->toBe($okStatus)
        ->and(TimeEntry::count())->toBe(0);
})->with(['not completable by the actor', 'would go to validation', 'in validation']);

it('AC-004: without a time entry, the children close too when their type does not require one', function () {
    $actor = cascadeActor();
    $optional = TaskType::factory()->create(['requires_time_entry' => false]);
    $parent = Task::factory()->forCreator($actor)->create(['task_type_id' => $optional->id]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create(['task_type_id' => $optional->id]);

    postCascadeComplete($parent, [])->assertOk();

    expect($child->fresh()->task_status_id)->toBe(cascadeClosedPositiveId())
        ->and(TimeEntry::count())->toBe(0);
});

it('AC-005: shares are prohibited on a task without open sub-tasks', function () {
    $actor = cascadeActor();
    $task = Task::factory()->forCreator($actor)->create();

    postCascadeComplete($task, [
        'time_entry' => validTimeEntryPayload(['minutes' => 60]),
        'time_entry_shares' => cascadeShares([$task], [60]),
    ])->assertStatus(422)->assertJsonValidationErrors(['time_entry_shares']);
});

it('AC-007: open-subtasks is 403 without complete on the parent', function () {
    $actor = taskCompletionActorWith(['view']);
    Sanctum::actingAs($actor);
    $parent = Task::factory()->forCreator($actor)->create();
    Task::factory()->childOf($parent)->create();

    $this->getJson("/api/tasks/{$parent->id}/open-subtasks")->assertForbidden();
});
