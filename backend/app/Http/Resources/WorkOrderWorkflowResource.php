<?php

namespace App\Http\Resources;

use App\Models\WorkOrderWorkflow;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionValueLabelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkOrderWorkflow
 */
class WorkOrderWorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Container-resolved: a JsonResource is instantiated directly, so
        // constructor injection is not available here.
        $valueLabels = app(WorkOrderCriterionValueLabelResolver::class)->resolve($this->criteria);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'criteria' => $this->criteria->map(fn ($criterion): array => [
                'id' => $criterion->id,
                'field' => $criterion->field,
                'value_id' => $criterion->value_id,
                'value_label' => $valueLabels[$criterion->id],
            ])->all(),
            'statuses' => WorkOrderWorkflowStatusResource::collection($this->statuses)->resolve(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
