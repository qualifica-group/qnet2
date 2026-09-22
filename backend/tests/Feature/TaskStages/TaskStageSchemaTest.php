<?php

use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Schema and relations of `task_template_stages`/`work_order_stages` and
| their FKs into `task_template_items`/`tasks` (spec 0146, BE-1)
|--------------------------------------------------------------------------
*/

// ---------------------------------------------------------------------------
// Columns
// ---------------------------------------------------------------------------

it('task_template_stages carries every column of the data_contract', function () {
    $expected = ['id', 'task_template_id', 'name', 'sort_order', 'created_at', 'updated_at'];

    expect(Schema::getColumnListing('task_template_stages'))->toEqualCanonicalizing($expected);
});

it('work_order_stages carries every column of the data_contract', function () {
    $expected = [
        'id', 'work_order_id', 'name', 'sort_order', 'closed_at', 'closed_by_id',
        'created_at', 'updated_at',
    ];

    expect(Schema::getColumnListing('work_order_stages'))->toEqualCanonicalizing($expected);
});

it('task_template_items gained task_template_stage_id', function () {
    expect(Schema::hasColumn('task_template_items', 'task_template_stage_id'))->toBeTrue();
});

it('tasks gained work_order_stage_id and stage_position', function () {
    expect(Schema::hasColumn('tasks', 'work_order_stage_id'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'stage_position'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Relations
// ---------------------------------------------------------------------------

it('TaskTemplate::stages() returns the rows ordered by sort_order, regardless of insertion order', function () {
    $template = TaskTemplate::factory()->create();
    $b = TaskTemplateStage::factory()->forTemplate($template)->atPosition(1)->create(['name' => 'B']);
    $a = TaskTemplateStage::factory()->forTemplate($template)->atPosition(0)->create(['name' => 'A']);

    expect($template->stages()->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('TaskTemplateStage::template() resolves the owning header', function () {
    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create();

    expect($stage->template()->first()->is($template))->toBeTrue();
});

it('TaskTemplateStage::items() returns only its own items, in sort_order', function () {
    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create();
    $b = TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['task_template_stage_id' => $stage->id]);
    $a = TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['task_template_stage_id' => $stage->id]);
    TaskTemplateItem::factory()->forTemplate($template)->create(); // unstaged, must not appear

    expect($stage->items()->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('TaskTemplateItem::stage() resolves the owning stage, null when unset', function () {
    $stage = TaskTemplateStage::factory()->create();
    $item = TaskTemplateItem::factory()->create(['task_template_stage_id' => $stage->id]);
    $unstaged = TaskTemplateItem::factory()->create();

    expect($item->stage()->first()->is($stage))->toBeTrue()
        ->and($unstaged->stage()->first())->toBeNull();
});

it('WorkOrder::stages() returns the rows ordered by sort_order, regardless of insertion order', function () {
    $workOrder = WorkOrder::factory()->create();
    $b = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(1)->create(['name' => 'B']);
    $a = WorkOrderStage::factory()->forWorkOrder($workOrder)->atPosition(0)->create(['name' => 'A']);

    expect($workOrder->stages()->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('WorkOrder::tasks() returns every task linked to the commessa, root and sub-task alike', function () {
    $workOrder = WorkOrder::factory()->create();
    $root = Task::factory()->create(['work_order_id' => $workOrder->id]);
    $sub = Task::factory()->childOf($root)->create(['work_order_id' => $workOrder->id]);
    Task::factory()->create(); // unrelated, must not appear

    expect($workOrder->tasks()->pluck('id')->all())->toEqualCanonicalizing([$root->id, $sub->id]);
});

it('WorkOrderStage::workOrder() resolves the owning commessa', function () {
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    expect($stage->workOrder()->first()->is($workOrder))->toBeTrue();
});

it('WorkOrderStage::tasks() returns only its own root tasks, ordered by stage_position', function () {
    $stage = WorkOrderStage::factory()->create();
    $b = Task::factory()->create(['work_order_stage_id' => $stage->id, 'stage_position' => 1]);
    $a = Task::factory()->create(['work_order_stage_id' => $stage->id, 'stage_position' => 0]);
    Task::factory()->create(); // unstaged, must not appear

    expect($stage->tasks()->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('WorkOrderStage::closedBy() resolves the closing user, null on an open stage', function () {
    $user = User::factory()->create();
    $closed = WorkOrderStage::factory()->create(['closed_at' => now(), 'closed_by_id' => $user->id]);
    $open = WorkOrderStage::factory()->create();

    expect($closed->closedBy()->first()->is($user))->toBeTrue()
        ->and($open->closedBy()->first())->toBeNull();
});

it('WorkOrderStage::isClosed() reflects closed_at', function () {
    $open = WorkOrderStage::factory()->create();
    $closed = WorkOrderStage::factory()->closed()->create();

    expect($open->isClosed())->toBeFalse()
        ->and($closed->isClosed())->toBeTrue();
});

it('Task::workOrderStage() resolves the owning stage, null on an unstaged task', function () {
    $stage = WorkOrderStage::factory()->create();
    $task = Task::factory()->create(['work_order_stage_id' => $stage->id]);
    $unstaged = Task::factory()->create();

    expect($task->workOrderStage()->first()->is($stage))->toBeTrue()
        ->and($unstaged->workOrderStage()->first())->toBeNull();
});

// ---------------------------------------------------------------------------
// FK behaviour
// ---------------------------------------------------------------------------

it('deleting a template cascades its stages at the database level (cascadeOnDelete)', function () {
    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create();

    $template->delete();

    $this->assertDatabaseMissing('task_template_stages', ['id' => $stage->id]);
});

it('deleting a template stage demotes its items to "Senza fase" (nullOnDelete), never deletes them', function () {
    $template = TaskTemplate::factory()->create();
    $stage = TaskTemplateStage::factory()->forTemplate($template)->create();
    $item = TaskTemplateItem::factory()->forTemplate($template)->create(['task_template_stage_id' => $stage->id]);

    $stage->delete();

    $this->assertDatabaseHas('task_template_items', ['id' => $item->id, 'task_template_stage_id' => null]);
});

it('deleting a commessa cascades its stages at the database level (cascadeOnDelete)', function () {
    $workOrder = WorkOrder::factory()->create();
    $stage = WorkOrderStage::factory()->forWorkOrder($workOrder)->create();

    $workOrder->delete();

    $this->assertDatabaseMissing('work_order_stages', ['id' => $stage->id]);
});

it('deleting a commessa stage demotes its tasks to "Senza fase" (nullOnDelete), never deletes them', function () {
    $stage = WorkOrderStage::factory()->create();
    $task = Task::factory()->create(['work_order_stage_id' => $stage->id]);

    $stage->delete();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'work_order_stage_id' => null]);
});

it('deleting the closing user leaves a closed stage in place, only its attribution clears (nullOnDelete)', function () {
    $user = User::factory()->create();
    $stage = WorkOrderStage::factory()->create(['closed_at' => now(), 'closed_by_id' => $user->id]);

    $user->delete();

    $this->assertDatabaseHas('work_order_stages', ['id' => $stage->id, 'closed_by_id' => null]);
});

// ---------------------------------------------------------------------------
// Migrations are reversible
// ---------------------------------------------------------------------------

it('the four migrations roll back and re-apply cleanly, in dependency order', function () {
    $tasksColumns = require database_path('migrations/2026_09_22_110300_add_stage_columns_to_tasks_table.php');
    $workOrderStages = require database_path('migrations/2026_09_22_110200_create_work_order_stages_table.php');
    $itemsColumn = require database_path('migrations/2026_09_22_110100_add_task_template_stage_id_to_task_template_items_table.php');
    $templateStages = require database_path('migrations/2026_09_22_110000_create_task_template_stages_table.php');

    $tasksColumns->down();
    $workOrderStages->down();
    $itemsColumn->down();
    $templateStages->down();

    expect(Schema::hasColumn('tasks', 'work_order_stage_id'))->toBeFalse()
        ->and(Schema::hasColumn('tasks', 'stage_position'))->toBeFalse()
        ->and(Schema::hasTable('work_order_stages'))->toBeFalse()
        ->and(Schema::hasColumn('task_template_items', 'task_template_stage_id'))->toBeFalse()
        ->and(Schema::hasTable('task_template_stages'))->toBeFalse();

    $templateStages->up();
    $itemsColumn->up();
    $workOrderStages->up();
    $tasksColumns->up();

    expect(Schema::hasTable('task_template_stages'))->toBeTrue()
        ->and(Schema::hasColumn('task_template_items', 'task_template_stage_id'))->toBeTrue()
        ->and(Schema::hasTable('work_order_stages'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'work_order_stage_id'))->toBeTrue()
        ->and(Schema::hasColumn('tasks', 'stage_position'))->toBeTrue()
        ->and(DB::table('task_template_stages')->count())->toBe(0);
});
