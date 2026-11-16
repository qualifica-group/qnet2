<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0399: POST /api/work-orders/{workOrder}/task-template/reload.
 */
uses(RefreshDatabase::class);

function reloadTemplateWithItems(array $titles, ?string $stageName = null): TaskTemplate
{
    $template = TaskTemplate::factory()->create();
    $stage = null;

    if ($stageName !== null) {
        $stage = TaskTemplateStage::factory()->forTemplate($template)->atPosition(0)->create(['name' => $stageName]);
    }

    foreach ($titles as $position => $title) {
        TaskTemplateItem::factory()->forTemplate($template)->atPosition($position)->create([
            'title' => $title,
            'due_offset_days' => 0,
            'task_template_stage_id' => $stage?->id,
        ]);
    }

    return $template;
}

function reloadEndpoint(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/task-template/reload";
}

function templateGeneratedTask(WorkOrder $workOrder, array $attributes = [], ?TaskStatus $status = null): Task
{
    $task = Task::factory()
        ->inStatus($status ?? TaskStatus::factory()->group(TaskStatusGroup::Open)->create())
        ->create(['work_order_id' => $workOrder->id, ...$attributes]);
    $task->is_template_generated = true;
    $task->save();

    return $task;
}

it('AC-001: loads a template on a commessa without one and keeps its manual tasks', function () {
    $workOrder = WorkOrder::factory()->withSupervisors()->create(['start_date' => '2026-11-01']);
    $manual = Task::factory()->create(['work_order_id' => $workOrder->id, 'title' => 'Manuale']);
    $template = reloadTemplateWithItems(['Uno', 'Due']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $template->id])
        ->assertOk()
        ->assertJsonPath('data.task_template', ['id' => $template->id, 'name' => $template->name]);

    expect($workOrder->fresh()->task_template_id)->toBe($template->id)
        ->and(Task::query()->whereKey($manual->id)->exists())->toBeTrue()
        ->and(Task::query()->where('work_order_id', $workOrder->id)->where('is_template_generated', true)->pluck('title')->sort()->values()->all())
        ->toBe(['Due', 'Uno']);
});

it('AC-002: replaces the open tasks of the previous template, manual sub-tasks included', function () {
    $previous = reloadTemplateWithItems(['Vecchio']);
    $workOrder = WorkOrder::factory()->withSupervisors()->create(['task_template_id' => $previous->id]);
    $old = templateGeneratedTask($workOrder, ['title' => 'Vecchio']);
    $manualChild = Task::factory()->childOf($old)->create(['work_order_id' => $workOrder->id]);
    $manualRoot = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $next = reloadTemplateWithItems(['Nuovo']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $next->id])->assertOk();

    expect(Task::query()->whereKey([$old->id, $manualChild->id])->exists())->toBeFalse()
        ->and(Task::query()->whereKey($manualRoot->id)->exists())->toBeTrue()
        ->and($workOrder->fresh()->task_template_id)->toBe($next->id)
        ->and(Task::query()->where('work_order_id', $workOrder->id)->where('is_template_generated', true)->pluck('title')->all())
        ->toBe(['Nuovo']);
});

it('AC-003: refuses with 409 when a task of the template, or one of its sub-tasks, is completed', function (TaskStatusGroup $group, bool $onChild) {
    $previous = reloadTemplateWithItems(['Vecchio']);
    $workOrder = WorkOrder::factory()->withSupervisors()->create(['task_template_id' => $previous->id]);
    $closed = TaskStatus::factory()->group($group)->create();
    $old = templateGeneratedTask($workOrder, [], $onChild ? null : $closed);

    if ($onChild) {
        Task::factory()->childOf($old)->inStatus($closed)->create(['work_order_id' => $workOrder->id]);
    }

    $next = reloadTemplateWithItems(['Nuovo']);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $next->id])
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data.completed_tasks_count', 1);

    expect(Task::query()->whereKey($old->id)->exists())->toBeTrue()
        ->and($workOrder->fresh()->task_template_id)->toBe($previous->id)
        ->and(Task::query()->where('work_order_id', $workOrder->id)->count())->toBe($onChild ? 2 : 1);
})->with([
    'closed positive root' => [TaskStatusGroup::ClosedPositive, false],
    'closed negative root' => [TaskStatusGroup::ClosedNegative, false],
    'closed sub-task' => [TaskStatusGroup::ClosedPositive, true],
]);

it('AC-004: drops the stages left empty, keeps those with manual work, reuses a stage of the same name', function () {
    $workOrder = WorkOrder::factory()->withSupervisors()->create();
    $emptied = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create(['name' => 'Analisi']);
    $kept = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(1)->create(['name' => 'Sviluppo']);
    templateGeneratedTask($workOrder, ['work_order_stage_id' => $emptied->id]);
    templateGeneratedTask($workOrder, ['work_order_stage_id' => $kept->id, 'stage_position' => 0]);
    $manual = Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $kept->id, 'stage_position' => 1]);
    $next = reloadTemplateWithItems(['Nuovo'], stageName: 'Sviluppo');
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $next->id])->assertOk();

    $generated = Task::query()->where('work_order_id', $workOrder->id)->where('is_template_generated', true)->sole();

    expect(WorkOrderStage::query()->whereKey($emptied->id)->exists())->toBeFalse()
        ->and($workOrder->stages()->pluck('name')->all())->toBe(['Sviluppo'])
        ->and($generated->work_order_stage_id)->toBe($kept->id)
        ->and($generated->stage_position)->toBe(2)
        ->and($manual->fresh()->work_order_stage_id)->toBe($kept->id);
});

it('AC-004: appends the new template stages after the existing ones', function () {
    $workOrder = WorkOrder::factory()->withSupervisors()->create();
    $manualStage = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create(['name' => 'Manuale']);
    Task::factory()->create(['work_order_id' => $workOrder->id, 'work_order_stage_id' => $manualStage->id]);
    $next = reloadTemplateWithItems(['Nuovo'], stageName: 'Collaudo');
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $next->id])->assertOk();

    expect($workOrder->stages()->get(['name', 'sort_order'])->map->only(['name', 'sort_order'])->all())
        ->toBe([['name' => 'Manuale', 'sort_order' => 0], ['name' => 'Collaudo', 'sort_order' => 1]]);
});

it('AC-005: 403 without work-orders.update', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => reloadTemplateWithItems(['Uno'])->id])
        ->assertForbidden();

    expect(Task::query()->where('work_order_id', $workOrder->id)->exists())->toBeFalse();
});

it('AC-005: 409 on a closed commessa', function () {
    $workOrder = WorkOrder::factory()->forceClosed()->create();
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => reloadTemplateWithItems(['Uno'])->id])
        ->assertStatus(409);

    expect($workOrder->fresh()->task_template_id)->toBeNull();
});

it('AC-005: 422 on a missing or inactive template', function () {
    $workOrder = WorkOrder::factory()->create();
    $inactive = TaskTemplate::factory()->create(['is_active' => false]);
    Sanctum::actingAs(workOrderUserWith(['view', 'update']));

    $this->postJson(reloadEndpoint($workOrder), [])->assertUnprocessable()->assertJsonValidationErrors('task_template_id');
    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => $inactive->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('task_template_id');
});

it('does not let a user from outside the commessa perimeter reload it', function () {
    $workOrder = WorkOrder::factory()->create();
    $user = User::factory()->create();
    foreach (['view', 'update'] as $ability) {
        $user->givePermissionTo(Permission::findOrCreate("work-orders.{$ability}"));
    }
    Sanctum::actingAs($user);

    $this->postJson(reloadEndpoint($workOrder), ['task_template_id' => reloadTemplateWithItems(['Uno'])->id])
        ->assertForbidden();
});
