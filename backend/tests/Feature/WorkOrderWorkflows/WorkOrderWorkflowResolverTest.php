<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Services\WorkOrders\WorkOrderWorkflowResolver;
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

    // The flag is process-wide: reset it, or every later test in this worker inherits it.
    Model::preventLazyLoading();

    try {
        $resolver = app(WorkOrderWorkflowResolver::class);
        $resolved = WorkOrder::query()->whereIn('id', $ids)->with('quoteLines.product')->get()
            ->map(fn (WorkOrder $workOrder) => $resolver->resolve($workOrder)?->name)->all();
    } finally {
        Model::preventLazyLoading(false);
    }

    expect($resolved)->toBe(['By function', 'By function', null]);
});

// AC-008 resolver part
it('picks the open row of the resolved set, then remaps by system key or falls back to open when the set changes', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $resolver = app(WorkOrderWorkflowResolver::class);
    $target = fn (WorkOrder $workOrder) => $resolver->targetStatus($workOrder, $resolver->resolve($workOrder));

    $workOrder = workOrderWithRevenueIn([$category]);
    expect($target($workOrder)->work_order_workflow_id)->toBe($workflow->id)
        ->and($target($workOrder)->system_key)->toBe('open');

    // Completed in the workflow's own set, then the lines go away: the global
    // row with the same system key is chosen.
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses->firstWhere('system_key', 'completed')->id])->save();
    $workOrder->quoteLines()->detach();
    expect($target($workOrder->fresh())->id)->toBe(globalWorkOrderStatus('completed')->id);

    // A custom status of a set that no longer applies falls back to open.
    $workOrder->forceFill(['work_order_workflow_status_id' => $workflow->statuses->firstWhere('name', 'Working')->id])->save();
    expect($target($workOrder->fresh())->id)->toBe(globalWorkOrderStatus('open')->id);
});

it('keeps the current status when it already belongs to the resolved set', function () {
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('By function', ['business_function_id' => $function->id]);
    $custom = $workflow->statuses->firstWhere('name', 'Working');
    $workOrder = workOrderWithRevenueIn([$category]);
    $workOrder->forceFill(['work_order_workflow_status_id' => $custom->id])->save();
    $resolver = app(WorkOrderWorkflowResolver::class);

    expect($resolver->targetStatus($workOrder->fresh(), $resolver->resolve($workOrder->fresh()))->id)->toBe($custom->id);
});
