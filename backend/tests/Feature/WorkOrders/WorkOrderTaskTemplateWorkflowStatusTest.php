<?php

use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\User;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0323, D-4/AC-004: the generator copies each template row's own
 * `work_order_workflow_status_id` onto the Task it generates.
 */
uses(RefreshDatabase::class);

it('AC-004: every generated task, root or sub-task, carries its own row link; unlinked rows stay null', function () {
    $statusA = WorkOrderWorkflowStatus::factory()->create(['progress_percentage' => 40]);
    $statusB = WorkOrderWorkflowStatus::factory()->create(['progress_percentage' => 70]);
    $template = TaskTemplate::factory()->create();

    $linkedRoot = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Radice collegata']);
    $linkedRoot->work_order_workflow_status_id = $statusA->id;
    $linkedRoot->save();

    $unlinkedChild = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)
        ->create(['title' => 'Figlia libera', 'parent_id' => $linkedRoot->id]);

    $linkedChild = TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)
        ->create(['title' => 'Figlia collegata', 'parent_id' => $linkedRoot->id]);
    $linkedChild->work_order_workflow_status_id = $statusB->id;
    $linkedChild->save();

    TaskTemplateItem::factory()->forTemplate($template)->atPosition(3)->create(['title' => 'Radice libera']);

    foreach (['viewAny', 'view', 'create', 'update'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor = User::factory()->create();
    $actor->givePermissionTo(['work-orders.create', 'work-orders.view']);
    Sanctum::actingAs($actor);

    $workOrderId = $this->postJson('/api/work-orders', [
        'quote_id' => Quote::factory()->create()->id, 'title' => 'Con stati', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [User::factory()->create()->id],
        'task_template_id' => $template->id,
    ])->assertCreated()->json('data.id');

    $byTitle = Task::query()->where('work_order_id', $workOrderId)->get()->keyBy('title');

    expect($byTitle)->toHaveCount(4)
        ->and($byTitle['Radice collegata']->work_order_workflow_status_id)->toBe($statusA->id)
        ->and($byTitle['Figlia collegata']->work_order_workflow_status_id)->toBe($statusB->id)
        ->and($byTitle['Figlia libera']->work_order_workflow_status_id)->toBeNull()
        ->and($byTitle['Radice libera']->work_order_workflow_status_id)->toBeNull();
});
