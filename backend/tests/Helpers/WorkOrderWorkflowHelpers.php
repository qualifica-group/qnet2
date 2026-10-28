<?php

declare(strict_types=1);

use App\DataObjects\WorkOrderWorkflows\CreateWorkOrderWorkflowData;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use App\Services\WorkOrderWorkflowService;

if (! function_exists('workOrderWorkflowFor')) {
    /**
     * Creates an active workflow through the real service (so the system rows
     * and ordering are the production ones) with the given criteria and
     * custom status names.
     *
     * @param  array<string, int>  $criteria  field => value_id
     * @param  array<int, string>  $customNames
     */
    function workOrderWorkflowFor(string $name, array $criteria, array $customNames = ['Working'], bool $active = true): WorkOrderWorkflow
    {
        return app(WorkOrderWorkflowService::class)->create(CreateWorkOrderWorkflowData::fromValidated([
            'name' => $name,
            'is_active' => $active,
            'criteria' => collect($criteria)->map(fn (int $valueId, string $field): array => ['field' => $field, 'value_id' => $valueId])->values()->all(),
            'statuses' => array_map(fn (string $status): array => ['name' => $status, 'group' => 'in_progress'], $customNames),
        ]));
    }
}

if (! function_exists('workOrderWithRevenueIn')) {
    /**
     * A work order whose scheduled lines are one REVENUE line per category
     * given, plus optionally COST lines in $costCategories (which must never
     * classify it).
     *
     * @param  array<int, ProductCategory>  $revenueCategories
     * @param  array<int, ProductCategory>  $costCategories
     */
    function workOrderWithRevenueIn(array $revenueCategories, array $costCategories = []): WorkOrder
    {
        $workOrder = WorkOrder::factory()->multiQuote()->create();
        $quote = Quote::factory()->create();

        foreach ($revenueCategories as $category) {
            $workOrder->quoteLines()->attach(QuoteLine::factory()->create([
                'quote_id' => $quote->id,
                'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            ])->id);
        }

        foreach ($costCategories as $category) {
            $workOrder->quoteLines()->attach(QuoteLine::factory()->cost()->create([
                'quote_id' => $quote->id,
                'product_id' => Product::factory()->create(['category_id' => $category->id])->id,
            ])->id);
        }

        return $workOrder->fresh();
    }
}

if (! function_exists('globalWorkOrderStatus')) {
    function globalWorkOrderStatus(string $systemKey): WorkOrderWorkflowStatus
    {
        return WorkOrderWorkflowStatus::query()
            ->whereNull('work_order_workflow_id')
            ->where('system_key', $systemKey)
            ->sole();
    }
}
