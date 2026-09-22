<?php

use App\Models\Quote;
use App\Models\Task;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateItem;
use App\Models\TaskTemplateStage;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Stage/position copy on Commessa creation from a Modello di Task (spec
// 0146, D-2). AC-004.
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

it('AC-004: copies the template stages in order and assigns each generated task its copied stage + dense stage_position', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();

    $stageOne = TaskTemplateStage::factory()->forTemplate($template)->atPosition(0)->create(['name' => 'Preparazione']);
    $stageTwo = TaskTemplateStage::factory()->forTemplate($template)->atPosition(1)->create(['name' => 'Esecuzione']);

    // Deliberately out of "logical" order: two rows in stage two, one in
    // stage one, one unstaged — stage_position must still be dense 0..n-1
    // WITHIN each group, in the order the items themselves are read.
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Esecuzione A', 'task_template_stage_id' => $stageTwo->id]);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'Preparazione A', 'task_template_stage_id' => $stageOne->id]);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(2)->create(['title' => 'Senza fase', 'task_template_stage_id' => null]);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(3)->create(['title' => 'Esecuzione B', 'task_template_stage_id' => $stageTwo->id]);

    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Con fasi', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $workOrder = WorkOrder::find($response->json('data.id'));

    $copiedStages = WorkOrderStage::query()->where('work_order_id', $workOrder->id)->orderBy('sort_order')->get();
    expect($copiedStages)->toHaveCount(2)
        ->and($copiedStages[0]->name)->toBe('Preparazione')
        ->and($copiedStages[0]->sort_order)->toBe(0)
        ->and($copiedStages[1]->name)->toBe('Esecuzione')
        ->and($copiedStages[1]->sort_order)->toBe(1);

    $tasksByTitle = Task::query()->where('work_order_id', $workOrder->id)->get()->keyBy('title');

    expect($tasksByTitle['Preparazione A']->work_order_stage_id)->toBe($copiedStages[0]->id)
        ->and($tasksByTitle['Preparazione A']->stage_position)->toBe(0)
        ->and($tasksByTitle['Esecuzione A']->work_order_stage_id)->toBe($copiedStages[1]->id)
        ->and($tasksByTitle['Esecuzione A']->stage_position)->toBe(0)
        ->and($tasksByTitle['Esecuzione B']->work_order_stage_id)->toBe($copiedStages[1]->id)
        ->and($tasksByTitle['Esecuzione B']->stage_position)->toBe(1)
        ->and($tasksByTitle['Senza fase']->work_order_stage_id)->toBeNull()
        ->and($tasksByTitle['Senza fase']->stage_position)->toBe(0);
});

it('AC-004: an unstaged template (no stages) copies none, every generated task lands in "Senza fase" with dense positions', function () {
    $quote = Quote::factory()->create();
    $supervisor = User::factory()->create();
    $template = TaskTemplate::factory()->create();
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(0)->create(['title' => 'Uno']);
    TaskTemplateItem::factory()->forTemplate($template)->atPosition(1)->create(['title' => 'Due']);

    $actor = taskTemplateGenerationActor(['create', 'view']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/work-orders', [
        'quote_id' => $quote->id, 'title' => 'Senza fasi', 'type' => 'processing',
        'start_date' => '2026-10-01', 'supervisor_ids' => [$supervisor->id],
        'task_template_id' => $template->id,
    ])->assertCreated();

    $workOrder = WorkOrder::find($response->json('data.id'));

    expect(WorkOrderStage::query()->where('work_order_id', $workOrder->id)->count())->toBe(0);

    $tasks = Task::query()->where('work_order_id', $workOrder->id)->orderBy('id')->get();
    expect($tasks->pluck('work_order_stage_id')->filter()->count())->toBe(0)
        ->and($tasks->pluck('stage_position')->all())->toBe([0, 1]);
});
