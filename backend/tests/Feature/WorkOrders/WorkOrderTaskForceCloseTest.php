<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Force-closing a Commessa force-closes its non-closed Tasks (spec 0146,
 * D-8), AC-021..023. Mirrors WorkOrderCrudTest's own permission helper.
 */
uses(RefreshDatabase::class);

if (! function_exists('forceCloseUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function forceCloseUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        // WorkOrderPolicy scopes view/update to the commessa's own
        // Responsabili/Partecipanti (WorkOrderVisibilityScope) unless the
        // actor holds `viewAll` — these tests are not about that scoping,
        // so it is widened here exactly as WorkOrderCrudTest's own helper
        // does.
        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

if (! function_exists('closedNegativeStatusId')) {
    /**
     * The PROTECTED `closed_negative` row WorkOrderTaskForceCloser resolves
     * by `system_key` (mirrors TaskCompletionService::systemStatusId()).
     * Memoized per test via `where` first: the migrations do not seed this
     * row in the testing database, and `system_key` is UNIQUE, so a second
     * call within the same test must reuse the row rather than re-create it.
     */
    function closedNegativeStatusId(): int
    {
        $existing = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedNegative->value)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) TaskStatus::factory()
            ->system(TaskStatusSystemKey::ClosedNegative)
            ->group(TaskStatusGroup::ClosedNegative)
            ->create()->id;
    }
}

it('AC-021: PATCH is_force_closed=true closes every non-closed task, leaves closed ones untouched', function () {
    closedNegativeStatusId();
    $actor = forceCloseUserWith(['update']);
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);

    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $pendingStatus = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    $inValidationStatus = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $closedPositiveStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    $open = Task::factory()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'closure_feedback' => null]);
    $pending = Task::factory()->inStatus($pendingStatus)->create(['work_order_id' => $workOrder->id, 'closure_feedback' => null]);
    $inValidation = Task::factory()->inStatus($inValidationStatus)->create(['work_order_id' => $workOrder->id, 'closure_feedback' => null]);
    $blocked = Task::factory()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'is_blocked' => true, 'closure_feedback' => null]);
    $subtask = Task::factory()->inStatus($openStatus)->childOf($open)->create(['work_order_id' => $workOrder->id, 'closure_feedback' => null]);
    // Already carries its own feedback: D-8 must not overwrite it.
    $withOwnFeedback = Task::factory()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'closure_feedback' => 'motivo originale']);
    // Already closed: must stay exactly as it is.
    $alreadyClosed = Task::factory()->inStatus($closedPositiveStatus)->create([
        'work_order_id' => $workOrder->id,
        'completion_date' => '2026-01-01',
        'closure_feedback' => 'gia chiuso',
    ]);

    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'is_force_closed' => true,
        'force_close_reason' => 'Commessa annullata',
    ])->assertOk();

    expect($response->json('data.is_force_closed'))->toBeTrue();

    $today = now()->toDateString();
    $closedNegativeId = closedNegativeStatusId();

    foreach ([$open, $pending, $inValidation, $blocked, $subtask] as $task) {
        $task->refresh();
        expect($task->task_status_id)->toBe($closedNegativeId)
            ->and($task->completion_date->toDateString())->toBe($today)
            ->and($task->closure_feedback)->toBe('Commessa annullata')
            ->and($task->is_blocked)->toBeFalse();
    }

    $withOwnFeedback->refresh();
    expect($withOwnFeedback->task_status_id)->toBe($closedNegativeId)
        ->and($withOwnFeedback->closure_feedback)->toBe('motivo originale');

    $alreadyClosed->refresh();
    expect($alreadyClosed->task_status_id)->toBe($closedPositiveStatus->id)
        ->and($alreadyClosed->completion_date->toDateString())->toBe('2026-01-01')
        ->and($alreadyClosed->closure_feedback)->toBe('gia chiuso');
});

it('AC-022: force close reaches invisible tasks, sends no notification/time entry, and survives a reopen', function () {
    closedNegativeStatusId();
    $actor = forceCloseUserWith(['update']);
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();

    // Neither creator, requester, assignee nor watcher of $actor, and $actor
    // holds no `tasks.viewAll` — TaskVisibilityScope would hide this row.
    $stranger = User::factory()->create();
    $invisible = Task::factory()->inStatus($openStatus)->forCreator($stranger)->create([
        'work_order_id' => $workOrder->id,
        'closure_feedback' => null,
    ]);

    Notification::fake();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'is_force_closed' => true,
        'force_close_reason' => 'Commessa annullata',
    ])->assertOk();

    Notification::assertNothingSent();
    expect(TimeEntry::query()->where('task_id', $invisible->id)->exists())->toBeFalse();

    $invisible->refresh();
    expect($invisible->task_status_id)->toBe(closedNegativeStatusId());

    // Reopening the commessa does NOT reopen the task (D-8, out of scope).
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false])
        ->assertOk()
        ->assertJsonPath('data.is_force_closed', false);

    $invisible->refresh();
    expect($invisible->task_status_id)->toBe(closedNegativeStatusId());
});

it('AC-023: open_tasks_count on the detail matches what a force close would touch', function () {
    closedNegativeStatusId();
    $actor = forceCloseUserWith(['view']);
    $workOrder = WorkOrder::factory()->create(['is_force_closed' => false]);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $closedPositiveStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();

    Task::factory()->inStatus($openStatus)->count(2)->create(['work_order_id' => $workOrder->id]);
    Task::factory()->inStatus($closedPositiveStatus)->create(['work_order_id' => $workOrder->id]);
    // Another commessa's open task must not leak into this count.
    Task::factory()->inStatus($openStatus)->create(['work_order_id' => WorkOrder::factory()->create()->id]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('data.open_tasks_count', 2);
});
