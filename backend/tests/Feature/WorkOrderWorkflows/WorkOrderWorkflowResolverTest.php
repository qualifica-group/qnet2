<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
use App\Services\WorkOrderWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function resolvedWorkflow(WorkOrder $workOrder): ?WorkOrderWorkflow
{
    return app(WorkOrderWorkflowResolver::class)->resolve($workOrder);
}

// AC-006
it('resolves a work order on the workflow of the INHERITED business function of its category', function () {
    $function = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $child = ProductCategory::factory()->childOf($root)->create();
    $grandchild = ProductCategory::factory()->childOf($child)->create();
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);

    $workOrder = workOrderWithRevenueIn([$grandchild]);

    expect(resolvedWorkflow($workOrder)?->id)->toBe($workflow->id);
});

it('matches product_category_id exactly and the branch criterion on ancestors', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    $exact = workOrderWorkflowFor('Exact', ['product_category_id' => $child->id]);
    $branch = workOrderWorkflowFor('Branch', ['product_category_branch_id' => $root->id]);

    expect(resolvedWorkflow(workOrderWithRevenueIn([$child]))?->id)->toBe($exact->id)
        ->and(resolvedWorkflow(workOrderWithRevenueIn([$root]))?->id)->toBe($branch->id);
});

// AC-007
it('ranks by number of criteria first', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    workOrderWorkflowFor('One criterion', ['business_function_id' => $function->id]);
    $two = workOrderWorkflowFor('Two criteria', ['business_function_id' => $function->id, 'product_category_id' => $category->id]);

    expect(resolvedWorkflow(workOrderWithRevenueIn([$category]))?->id)->toBe($two->id);
});

it('then prefers the closest category branch', function () {
    $root = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->childOf($root)->create();
    workOrderWorkflowFor('Far', ['product_category_branch_id' => $root->id]);
    $near = workOrderWorkflowFor('Near', ['product_category_branch_id' => $child->id]);

    expect(resolvedWorkflow(workOrderWithRevenueIn([$child]))?->id)->toBe($near->id);
});

it('then breaks ties on the lowest id', function () {
    $root = ProductCategory::factory()->create();
    $category = ProductCategory::factory()->childOf($root)->create();
    $first = workOrderWorkflowFor('First', ['product_category_id' => $category->id]);
    workOrderWorkflowFor('Second', ['product_category_branch_id' => $category->id]);

    expect(resolvedWorkflow(workOrderWithRevenueIn([$category]))?->id)->toBe($first->id);
});

it('resolves to the global set without REVENUE lines, with only COST lines, or with an inactive workflow', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    workOrderWorkflowFor('Active', ['business_function_id' => $function->id]);
    $inactiveCategory = ProductCategory::factory()->create();
    workOrderWorkflowFor('Inactive', ['product_category_id' => $inactiveCategory->id], active: false);

    expect(resolvedWorkflow(WorkOrder::factory()->create()))->toBeNull()
        ->and(resolvedWorkflow(workOrderWithRevenueIn([], [$category])))->toBeNull()
        ->and(resolvedWorkflow(workOrderWithRevenueIn([$inactiveCategory])))->toBeNull();
});

it('requires ALL criteria to match', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $other = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    workOrderWorkflowFor('Both', ['business_function_id' => $function->id, 'product_category_id' => $category->id]);

    expect(resolvedWorkflow(workOrderWithRevenueIn([$other])))->toBeNull();
});

it('resolves a batch with a single eager load and no lazy loading', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $ids = [workOrderWithRevenueIn([$category])->id, workOrderWithRevenueIn([$category])->id, workOrderWithRevenueIn([])->id];

    Model::preventLazyLoading();
    $resolver = app(WorkOrderWorkflowResolver::class);
    $resolved = WorkOrder::query()->whereIn('id', $ids)->with('quoteLines.product')->get()
        ->map(fn (WorkOrder $workOrder) => $resolver->resolve($workOrder)?->name)->all();

    expect($resolved)->toBe(['By function', 'By function', null]);
});

// AC-008 resolver part
it('assigns the open row of the resolved set, then remaps by system key or falls back to open when the set changes', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $resolver = app(WorkOrderWorkflowResolver::class);

    $workOrder = workOrderWithRevenueIn([$category]);
    $resolver->resolveAndAssign($workOrder);
    expect($workOrder->fresh()->workOrderWorkflowStatus->work_order_workflow_id)->toBe($workflow->id)
        ->and($workOrder->fresh()->workOrderWorkflowStatus->system_key)->toBe('open');

    // Completed in the workflow's own set, then the lines go away: the global
    // row with the same system key is chosen.
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses->firstWhere('system_key', 'completed')->id])->save();
    $workOrder->quoteLines()->detach();
    $resolver->resolveAndAssign($workOrder->fresh());
    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('completed')->id);

    // A custom status of a set that no longer applies falls back to open.
    $custom = $workflow->statuses->firstWhere('name', 'Working');
    $workOrder->forceFill(['work_order_workflow_status_id' => $custom->id])->save();
    $resolver->resolveAndAssign($workOrder->fresh());
    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('open')->id);
});

it('keeps the current status when it already belongs to the resolved set', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $custom = $workflow->statuses->firstWhere('name', 'Working');
    $workOrder = workOrderWithRevenueIn([$category]);
    $workOrder->forceFill(['work_order_workflow_status_id' => $custom->id])->save();

    app(WorkOrderWorkflowResolver::class)->resolveAndAssign($workOrder->fresh());

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($custom->id);
});

// AC-009
it('delete: reassigns the impacted work orders by system key, or open, and removes the workflow', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('Doomed', ['business_function_id' => $function->id]);

    $onCompleted = workOrderWithRevenueIn([$category]);
    $onCompleted->forceFill(['work_order_workflow_status_id' => $workflow->statuses->firstWhere('system_key', 'completed')->id])->save();
    $onCustom = workOrderWithRevenueIn([$category]);
    $onCustom->forceFill(['work_order_workflow_status_id' => $workflow->statuses->firstWhere('name', 'Working')->id])->save();
    $untouched = WorkOrder::factory()->create();

    app(WorkOrderWorkflowService::class)->delete($workflow);

    expect(WorkOrderWorkflow::query()->count())->toBe(0)
        ->and($onCompleted->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('completed')->id)
        ->and($onCustom->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('open')->id)
        ->and($untouched->fresh()->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('open')->id);
});

it('delete: moves a work order onto the next matching workflow when one exists', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $doomed = workOrderWorkflowFor('Doomed', ['product_category_id' => $category->id]);
    $fallback = workOrderWorkflowFor('Fallback', ['business_function_id' => $function->id]);
    $workOrder = workOrderWithRevenueIn([$category]);
    $workOrder->forceFill(['work_order_workflow_status_id' => $doomed->statuses->firstWhere('system_key', 'open')->id])->save();

    app(WorkOrderWorkflowService::class)->delete($doomed);

    expect($workOrder->fresh()->workOrderWorkflowStatus->work_order_workflow_id)->toBe($fallback->id);
});
