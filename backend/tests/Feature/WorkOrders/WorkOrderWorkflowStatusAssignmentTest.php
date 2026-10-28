<?php

use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * A REVENUE line of $quote whose product sits in a category of $function.
 */
function revenueLineIn(Quote $quote, BusinessFunction $function): QuoteLine
{
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);

    return QuoteLine::factory()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
    ]);
}

// AC-008
it('create: omitted status gets the open row of the set resolved from the lines', function () {
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $quote = Quote::factory()->create();
    $line = revenueLineIn($quote, $function);
    Sanctum::actingAs(workOrderUserWith(['create']));

    $response = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'type' => 'processing', 'quote_line_ids' => [$line->id],
    ])->assertCreated();

    $open = $workflow->statuses()->where('system_key', 'open')->sole();

    expect($response->json('data.work_order_workflow_status_id'))->toBe($open->id)
        ->and($response->json('data.work_order_workflow_status.system_key'))->toBe('open');
});

it('create: without lines the commessa lands on the open row of the global set', function () {
    Sanctum::actingAs(workOrderUserWith(['create']));

    $response = $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => Quote::factory()->create()->id, 'type' => 'processing',
    ])->assertCreated();

    expect($response->json('data.work_order_workflow_status_id'))->toBe(globalWorkOrderStatus('open')->id);
});

it('create: a status of another set is 422 and nothing is written', function () {
    $foreign = workOrderWorkflowFor('Foreign', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    Sanctum::actingAs(workOrderUserWith(['create']));

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(),
        'quote_id' => Quote::factory()->create()->id,
        'type' => 'processing',
        'work_order_workflow_status_id' => $foreign->statuses()->where('system_key', 'open')->value('id'),
    ])->assertStatus(422)->assertJsonValidationErrors('work_order_workflow_status_id');

    expect(WorkOrder::query()->count())->toBe(0);
});

it('create: a status of the resolved set is accepted', function () {
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id], ['Working']);
    $quote = Quote::factory()->create();
    $line = revenueLineIn($quote, $function);
    $working = $workflow->statuses()->where('name', 'Working')->sole();
    Sanctum::actingAs(workOrderUserWith(['create']));

    $this->postJson('/api/work-orders', [
        ...workOrderRequiredFields(), 'quote_id' => $quote->id, 'type' => 'processing',
        'quote_line_ids' => [$line->id], 'work_order_workflow_status_id' => $working->id,
    ])->assertCreated()->assertJsonPath('data.work_order_workflow_status_id', $working->id);
});

it('update: changing the lines re-resolves the set and keeps the system_key', function () {
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $line = revenueLineIn($quote, $function);
    Sanctum::actingAs(workOrderUserWith(['update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => [$line->id]])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status_id', $workflow->statuses()->where('system_key', 'open')->value('id'));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['quote_line_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status_id', globalWorkOrderStatus('open')->id);
});

// AC-010
it('update: a status of another set is 422, one of its own set is 200 with an activity entry', function () {
    $function = BusinessFunction::factory()->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id], ['Working']);
    $foreign = workOrderWorkflowFor('Foreign', ['business_function_id' => BusinessFunction::factory()->create()->id]);
    $quote = Quote::factory()->create();
    $line = revenueLineIn($quote, $function);
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($line->id);
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses()->where('system_key', 'open')->value('id')])->save();
    $working = $workflow->statuses()->where('name', 'Working')->sole();
    Sanctum::actingAs(workOrderUserWith(['update']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", [
        'work_order_workflow_status_id' => $foreign->statuses()->where('system_key', 'open')->value('id'),
    ])->assertStatus(422)->assertJsonValidationErrors('work_order_workflow_status_id');

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['work_order_workflow_status_id' => $working->id])
        ->assertOk()
        ->assertJsonPath('data.work_order_workflow_status.name', 'Working');

    $entry = Activity::query()->where('subject_type', $workOrder->getMorphClass())->where('subject_id', $workOrder->id)
        ->where('description', 'Work order working status change')->sole();

    expect($entry->properties['attributes']['work_order_workflow_status_id'])->toBe($working->id)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($working->id);
});

it('update: 403 without the update permission', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $this->patchJson("/api/work-orders/{$workOrder->id}", ['work_order_workflow_status_id' => globalWorkOrderStatus('open')->id])
        ->assertForbidden();
});

// AC-011
it('detail: exposes the status, its resolved set and leaves the computed status alone', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderUserWith(['view']));

    $response = $this->getJson("/api/work-orders/{$workOrder->id}")->assertOk();

    expect($response->json('data.work_order_workflow_status_id'))->toBe(globalWorkOrderStatus('open')->id)
        ->and(array_keys($response->json('data.work_order_workflow_status')))->toBe(['id', 'name', 'color', 'group', 'system_key', 'progress_percentage', 'description'])
        ->and(collect($response->json('data.work_order_workflow_statuses'))->pluck('system_key')->all())->toBe(['open', 'completed', 'closed'])
        ->and(array_keys($response->json('data.work_order_workflow_statuses.0')))->toBe(['id', 'name', 'description', 'color', 'group', 'system_key', 'progress_percentage', 'sort_order'])
        ->and($response->json('data.status.value'))->toBe('open');
});
