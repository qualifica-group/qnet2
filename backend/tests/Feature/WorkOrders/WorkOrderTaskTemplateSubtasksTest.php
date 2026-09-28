<?php

use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Sub-item -> sub-task generation on Commessa creation (spec 0172, D-1,
 * AC-010) — `App\Services\WorkOrders\WorkOrderTaskGenerator`. Companion to
 * WorkOrderTaskTemplateGenerationTest (spec 0124, root-only rows).
 */
uses(RefreshDatabase::class);

if (! function_exists('taskTemplateGenerationActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskTemplateGenerationActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        return $user;
    }
}

it('AC-010: a 3-level sub-tree under a staged root and a "Senza fase" root generate the correct sub-tasks', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->atPosition(0)->create(['name' => 'Fase 1']);

    // Root A (staged, sort_order 0), a 3-level chain under A1, plus a second
    // direct child A2 to prove subtask_position is 0..n PER direct parent.
    $rootA = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)
        ->create(['title' => 'Radice A', 'due_offset_days' => 10, 'task_template_stage_id' => $stage->id]);
    $childA1 = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)
        ->create(['title' => 'A1', 'due_offset_days' => 5, 'parent_id' => $rootA->id]);
    $grandA1a = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)
        ->create(['title' => 'A1a', 'due_offset_days' => 5, 'parent_id' => $childA1->id]);
    $greatA1a1 = TaskTemplateItem::factory()->forTemplate($template)->atPosition(3)
        ->create(['title' => 'A1a1', 'due_offset_days' => 5, 'parent_id' => $grandA1a->id]);
    $childA2 = TaskTemplateItem::factory()->forTemplate($template)->atPosition(4)
        ->create(['title' => 'A2', 'due_offset_days' => 3, 'parent_id' => $rootA->id]);

    // Root C (staged, AFTER the whole A sub-tree in sort_order): proves the
    // root stage_position counter is NEVER shifted by A's own 4 sub-items.
    $rootC = TaskTemplateItem::factory()->forTemplate($template)->atPosition(5)
        ->create(['title' => 'Radice C', 'due_offset_days' => 1, 'task_template_stage_id' => $stage->id]);

    // Root B ("Senza fase") with its own direct child.
    $rootB = TaskTemplateItem::factory()->forTemplate($template)->atPosition(6)
        ->create(['title' => 'Radice B', 'due_offset_days' => 0]);
    $childB1 = TaskTemplateItem::factory()->forTemplate($template)->atPosition(7)
        ->create(['title' => 'B1', 'due_offset_days' => 0, 'parent_id' => $rootB->id]);

    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Con sotto-task', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $workOrderId = $response->json('data.id');
    $tasksByTitle = Task::query()->where('work_order_id', $workOrderId)->get()->keyBy('title');
    expect($tasksByTitle)->toHaveCount(8);

    $taskA = $tasksByTitle['Radice A'];
    $taskA1 = $tasksByTitle['A1'];
    $taskA1a = $tasksByTitle['A1a'];
    $taskA1a1 = $tasksByTitle['A1a1'];
    $taskA2 = $tasksByTitle['A2'];
    $taskC = $tasksByTitle['Radice C'];
    $taskB = $tasksByTitle['Radice B'];
    $taskB1 = $tasksByTitle['B1'];

    $copiedStageId = WorkOrder::find($workOrderId)->stages()->sole()->id;

    // Roots: parent_task_id null, staged root resolves the copied
    // WorkOrderStage, "Senza fase" root stays null.
    expect($taskA->parent_task_id)->toBeNull()
        ->and($taskA->work_order_stage_id)->toBe($copiedStageId)
        ->and($taskB->parent_task_id)->toBeNull()
        ->and($taskB->work_order_stage_id)->toBeNull();

    // AC-010: root stage_position counts ROOTS only — A is first (0), C is
    // second (1) despite A's 4 sub-items sitting between them in sort_order.
    // B is the first (and only) root in "Senza fase" (0).
    expect($taskA->stage_position)->toBe(0)
        ->and($taskC->stage_position)->toBe(1)
        ->and($taskC->work_order_stage_id)->toBe($copiedStageId)
        ->and($taskB->stage_position)->toBe(0);

    // Sub-tasks: parent_task_id chains to the DIRECT parent's own generated
    // Task, never a stage, stage_position stays at the neutral value (0).
    foreach ([$taskA1, $taskA1a, $taskA1a1, $taskA2, $taskB1] as $subtask) {
        expect($subtask->work_order_stage_id)->toBeNull()
            ->and($subtask->stage_position)->toBe(0);
    }

    expect($taskA1->parent_task_id)->toBe($taskA->id)
        ->and($taskA2->parent_task_id)->toBe($taskA->id)
        ->and($taskA1a->parent_task_id)->toBe($taskA1->id)
        ->and($taskA1a1->parent_task_id)->toBe($taskA1a->id)
        ->and($taskB1->parent_task_id)->toBe($taskB->id);

    // subtask_position: 0..n among the rows sharing the SAME direct parent,
    // in item order — A1 before A2 (siblings of A), the rest are only
    // children so their position is 0.
    expect($taskA1->subtask_position)->toBe(0)
        ->and($taskA2->subtask_position)->toBe(1)
        ->and($taskA1a->subtask_position)->toBe(0)
        ->and($taskA1a1->subtask_position)->toBe(0)
        ->and($taskB1->subtask_position)->toBe(0);

    // end_date = commessa start_date + the ROW'S OWN offset (not cumulative
    // through the chain), same rule as a root.
    expect($taskA->end_date->toDateString())->toBe('2026-10-11')
        ->and($taskA1->end_date->toDateString())->toBe('2026-10-06')
        ->and($taskA1a->end_date->toDateString())->toBe('2026-10-06')
        ->and($taskA1a1->end_date->toDateString())->toBe('2026-10-06')
        ->and($taskA2->end_date->toDateString())->toBe('2026-10-04')
        ->and($taskB->end_date->toDateString())->toBe('2026-10-01')
        ->and($taskB1->end_date->toDateString())->toBe('2026-10-01');

    // Assignees: sub-tasks get the commessa's own supervisors too, same as
    // roots — never inherited from a parent's OWN assignee set.
    expect($taskA1a1->assignees()->pluck('users.id')->all())->toBe([$supervisor->id])
        ->and($taskB1->assignees()->pluck('users.id')->all())->toBe([$supervisor->id]);
});
