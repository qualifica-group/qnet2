<?php

use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskClosed;
use App\Notifications\TaskFeedbackInserted;
use App\Notifications\TasksClosedSummaryNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| One closure summary per recipient for a multi-task operation (spec 0428)
|--------------------------------------------------------------------------
| Bulk Completa (tasks module and work order board) and a parent closing its
| open sub-tasks send each recipient ONE TasksClosedSummaryNotification in
| place of one TaskClosed/TaskFeedbackInserted per task.
*/

/**
 * @return array{actor: User, requester: User, watcher: User, workOrder: WorkOrder, tasks: array<int, Task>}
 */
function closureSummaryScenario(): array
{
    $actor = taskCompletionActorWith(['view', 'complete']);

    foreach (['view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

    $requester = User::factory()->create();
    $watcher = User::factory()->create();
    $workOrder = WorkOrder::factory()->create();
    $tasks = Task::factory()->count(3)->forCreator($actor)->create([
        'work_order_id' => $workOrder->id,
        'requester_id' => $requester->id,
        'title' => 'Fase',
    ])->all();
    $tasks[0]->watchers()->attach($watcher->id);

    Sanctum::actingAs($actor);

    return ['actor' => $actor, 'requester' => $requester, 'watcher' => $watcher, 'workOrder' => $workOrder, 'tasks' => $tasks];
}

/**
 * @param  array<int, Task>  $tasks
 * @return array<string, mixed>
 */
function closureSummaryPayload(array $tasks, ?string $feedback = 'COMPLETA'): array
{
    return [
        'action' => 'complete',
        'task_ids' => array_map(fn (Task $task) => $task->id, $tasks),
        'closure_feedback' => $feedback,
        'time_entry' => validTimeEntryPayload(['minutes' => count($tasks) * 10]),
        'time_entry_shares' => array_map(fn (Task $task) => ['task_id' => $task->id, 'minutes' => 10], $tasks),
    ];
}

function closureSummaryUrl(string $endpoint, WorkOrder $workOrder): string
{
    return $endpoint === 'tasks' ? '/api/tasks/bulk' : "/api/work-orders/{$workOrder->id}/task-board/bulk";
}

dataset('closureSummaryEndpoints', ['tasks module' => ['tasks'], 'work order board' => ['board']]);

it('AC-001/AC-003: a bulk Completa sends the requester one summary instead of one email per task', function (string $endpoint) {
    Notification::fake();
    $s = closureSummaryScenario();

    $this->postJson(closureSummaryUrl($endpoint, $s['workOrder']), closureSummaryPayload($s['tasks']))->assertOk();

    Notification::assertSentToTimes($s['requester'], TasksClosedSummaryNotification::class, 1);
    Notification::assertNotSentTo($s['requester'], TaskFeedbackInserted::class);
    Notification::assertNotSentTo($s['requester'], TaskClosed::class);
    Notification::assertNothingSentTo($s['actor']);
})->with('closureSummaryEndpoints');

it('AC-002: a recipient of a single task of the batch keeps the per-task notification', function (string $endpoint) {
    Notification::fake();
    $s = closureSummaryScenario();

    $this->postJson(closureSummaryUrl($endpoint, $s['workOrder']), closureSummaryPayload($s['tasks']))->assertOk();

    Notification::assertSentToTimes($s['watcher'], TaskFeedbackInserted::class, 1);
    Notification::assertNotSentTo($s['watcher'], TasksClosedSummaryNotification::class);
})->with('closureSummaryEndpoints');

it('AC-005: a refused batch sends nothing', function (string $endpoint) {
    Notification::fake();
    $s = closureSummaryScenario();
    $s['tasks'][2]->forceFill(['requires_closure_feedback' => true])->save();

    $this->postJson(closureSummaryUrl($endpoint, $s['workOrder']), closureSummaryPayload($s['tasks'], null))->assertStatus(422);

    Notification::assertNothingSent();
})->with('closureSummaryEndpoints');

it('AC-003: a parent closing its open sub-tasks sends the requester one summary', function () {
    Notification::fake();
    $s = closureSummaryScenario();
    [$parent, $first, $second] = $s['tasks'];
    $first->forceFill(['parent_task_id' => $parent->id])->save();
    $second->forceFill(['parent_task_id' => $parent->id])->save();

    $this->postJson("/api/tasks/{$parent->id}/complete", [
        'closure_feedback' => 'COMPLETA',
        'time_entry' => validTimeEntryPayload(['minutes' => 30]),
        'time_entry_shares' => array_map(fn (Task $task) => ['task_id' => $task->id, 'minutes' => 10], [$parent, $first, $second]),
    ])->assertOk();

    Notification::assertSentToTimes($s['requester'], TasksClosedSummaryNotification::class, 1);
    Notification::assertNotSentTo($s['requester'], TaskFeedbackInserted::class);
});

it('AC-004: closing a single task still sends the per-task notification', function () {
    Notification::fake();
    $s = closureSummaryScenario();

    $this->postJson("/api/tasks/{$s['tasks'][1]->id}/complete", [
        'closure_feedback' => 'COMPLETA',
        'time_entry' => validTimeEntryPayload(),
    ])->assertOk();

    Notification::assertSentToTimes($s['requester'], TaskFeedbackInserted::class, 1);
    Notification::assertNotSentTo($s['requester'], TasksClosedSummaryNotification::class);
});

it('AC-006: the summary names the work order, counts the tasks and links the commessa only to who can open it', function () {
    Notification::fake();
    $s = closureSummaryScenario();
    $s['requester']->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);

    $this->postJson('/api/tasks/bulk', closureSummaryPayload($s['tasks']))->assertOk();

    App::setLocale('it');
    Notification::assertSentTo($s['requester'], TasksClosedSummaryNotification::class, function (TasksClosedSummaryNotification $notification) use ($s) {
        $payload = $notification->toArray($s['requester']);
        $mailLines = $notification->toMail($s['requester'])->introLines;
        $label = "{$s['workOrder']->code} - {$s['workOrder']->title}";

        return $payload['title'] === 'Attività completate'
            && $payload['message'] === "{$s['actor']->name} ha completato 3 attività della commessa {$label}."
            && $payload['action_url'] !== null
            && $notification->toArray($s['watcher'])['action_url'] === null
            && count(array_filter($mailLines, fn (string $line) => $line === '- Fase')) === 3;
    });
});
