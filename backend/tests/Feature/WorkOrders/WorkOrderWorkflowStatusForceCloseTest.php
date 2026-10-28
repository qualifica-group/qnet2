<?php

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * A work order of a quote whose REVENUE line sits in a category of a function
 * matched by its own workflow, parked on that set's open row.
 *
 * @return array{0: WorkOrder, 1: WorkOrderWorkflow}
 */
function workOrderOnOwnWorkflow(): array
{
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id], ['Working']);
    $quote = Quote::factory()->create();
    $line = QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create([
            'category_id' => ProductCategory::factory()->create(['business_function_id' => $function->id])->id,
        ])->id,
    ]);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($line->id);
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses()->where('system_key', 'open')->value('id')])->save();

    return [$workOrder, $workflow];
}

it('names the closing system rows "Chiusa con esito positivo" and "Chiusa con esito negativo"', function () {
    expect(globalWorkOrderStatus('completed')->name)->toBe('Chiusa con esito positivo')
        ->and(globalWorkOrderStatus('closed')->name)->toBe('Chiusa con esito negativo');
});

it('force-closing moves the status onto the closed row of its own set, with an activity entry', function () {
    [$workOrder, $workflow] = workOrderOnOwnWorkflow();
    Sanctum::actingAs(workOrderUserWith(['update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'Customer gone'])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status.system_key', 'closed')
        ->assertJsonPath('data.work_order_workflow_status_id', $workflow->statuses()->where('system_key', 'closed')->value('id'));

    expect(Activity::query()->where('subject_id', $workOrder->id)->where('description', 'Work order working status change')->count())->toBe(1);
});

it('force-closing wins over a status chosen in the same request', function () {
    [$workOrder, $workflow] = workOrderOnOwnWorkflow();
    Sanctum::actingAs(workOrderUserWith(['update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'is_force_closed' => true,
        'force_close_reason' => 'Customer gone',
        'work_order_workflow_status_id' => $workflow->statuses()->where('name', 'Working')->value('id'),
    ])->assertOk()->assertJsonPath('data.work_order_workflow_status.system_key', 'closed');
});

it('reopening moves a commessa still on the closed row back to open', function () {
    [$workOrder, $workflow] = workOrderOnOwnWorkflow();
    Sanctum::actingAs(workOrderUserWith(['update']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'Paused'])->assertOk();

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status_id', $workflow->statuses()->where('system_key', 'open')->value('id'));
});

it('reopening keeps a status changed by hand while closed', function () {
    [$workOrder, $workflow] = workOrderOnOwnWorkflow();
    $working = $workflow->statuses()->where('name', 'Working')->value('id');
    Sanctum::actingAs(workOrderUserWith(['update']));
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => true, 'force_close_reason' => 'Paused'])->assertOk();
    $this->patchJson("/api/work-orders/{$workOrder->id}", ['work_order_workflow_status_id' => $working])->assertOk();

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['is_force_closed' => false])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status_id', $working);
});

it('a commessa born force-closed starts on the closed row', function () {
    Sanctum::actingAs(workOrderUserWith(['create']));

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => Quote::factory()->create()->id,
        'type' => 'processing',
        'is_force_closed' => true,
        'force_close_reason' => 'Born closed',
    ])->assertCreated()->assertJsonPath('data.work_order_workflow_status_id', globalWorkOrderStatus('closed')->id);
});
