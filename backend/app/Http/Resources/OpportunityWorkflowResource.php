<?php

namespace App\Http\Resources;

use App\Models\OpportunityWorkflow;
use App\Support\OpportunityWorkflows\CriterionFieldRegistry;
use App\Support\OpportunityWorkflows\CriterionValueLabelResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OpportunityWorkflow
 */
class OpportunityWorkflowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Both registries are container-resolved (app()): a JsonResource is
        // instantiated directly (`new OpportunityWorkflowResource(...)`), so
        // constructor injection is not available here (spec 0047 amendment
        // 2026-07-27 constraint).
        $fieldsByKey = collect(app(CriterionFieldRegistry::class)->allowedFields())->keyBy('field');
        $valueLabels = app(CriterionValueLabelResolver::class)->resolve($this->criteria);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'criteria' => $this->criteria->map(function ($criterion) use ($fieldsByKey, $valueLabels): array {
                $fieldMeta = $fieldsByKey->get($criterion->field);

                return [
                    'id' => $criterion->id,
                    'field' => $criterion->field,
                    'value_id' => $criterion->value_id,
                    'value_label' => $valueLabels[$criterion->id],
                    // D10: a field no longer in the allow-list falls back to
                    // its raw key and 'native' (contract, amendment 2026-07-27).
                    'field_label' => $fieldMeta['label'] ?? $criterion->field,
                    'field_source' => $fieldMeta['source'] ?? 'native',
                ];
            })->all(),
            'statuses' => $this->statuses->map(fn ($status): array => [
                'id' => $status->id,
                'name' => $status->name,
                'description' => $status->description,
                'color' => $status->color,
                'sort_order' => $status->sort_order,
                'system_key' => $status->system_key,
                'group' => $status->group->value,
                'requires_note' => $status->requires_note,
            ])->all(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
