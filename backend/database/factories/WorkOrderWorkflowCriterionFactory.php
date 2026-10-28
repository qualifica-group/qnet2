<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderWorkflowCriterion>
 */
class WorkOrderWorkflowCriterionFactory extends Factory
{
    protected $model = WorkOrderWorkflowCriterion::class;

    /**
     * Default `field`/`value_id` pair targets `business_function_id` (the
     * simplest allow-listed criterion) — callers needing another field
     * override both keys via ->state().
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_workflow_id' => WorkOrderWorkflow::factory(),
            'field' => 'business_function_id',
            'value_id' => BusinessFunction::factory(),
        ];
    }
}
