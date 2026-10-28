<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderWorkflows\Concerns;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderWorkflowStatusSystemKey;
use App\Support\WorkOrderWorkflows\WorkOrderCriterionFieldRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the `criteria`/`statuses` payload of the
 * work-order-workflow write endpoints (spec 0322, twin of
 * ValidatesWorkflowCriteria): shape rules in criteriaRules()/statusesRules(),
 * cross-row invariants (distinct `field`, `value_id` exists for its field) in
 * validateCriteria(). The criteria-combination uniqueness is enforced by
 * WorkOrderWorkflowService (422), not here.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesWorkOrderWorkflowPayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function criteriaRules(bool $required): array
    {
        $allowedFields = app(WorkOrderCriterionFieldRegistry::class)->fieldNames();

        return [
            'criteria' => $required ? ['required', 'array', 'min:1'] : ['sometimes', 'array', 'min:1'],
            'criteria.*.field' => ['required', 'string', Rule::in($allowedFields)],
            'criteria.*.value_id' => ['required', 'integer'],
        ];
    }

    /**
     * @param  bool  $allowIds  update passes true (statuses.*.id identifies an
     *                          existing row); store passes false (a submitted status is either a
     *                          new custom row or a system row tagged by `system_key`).
     * @return array<string, array<int, mixed>>
     */
    protected function statusesRules(bool $allowIds): array
    {
        $rules = [
            'statuses' => ['sometimes', 'array'],
            'statuses.*.name' => ['required', 'string', 'max:191'],
            'statuses.*.description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'statuses.*.color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'statuses.*.group' => ['required', Rule::in(WorkOrderStatus::values())],
            'statuses.*.progress_percentage' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'statuses.*.system_key' => ['sometimes', 'nullable', Rule::in(WorkOrderWorkflowStatusSystemKey::values())],
        ];

        if ($allowIds) {
            $rules['statuses.*.id'] = ['sometimes', 'integer'];
        }

        return $rules;
    }

    protected function validateCriteria(Validator $validator): void
    {
        $criteria = $this->input('criteria');

        if (! is_array($criteria)) {
            return;
        }

        $this->assertDistinctFields($validator, $criteria);
        $this->assertValueIdsExist($validator, $criteria);
    }

    /**
     * @param  array<int, mixed>  $criteria
     */
    private function assertDistinctFields(Validator $validator, array $criteria): void
    {
        $seenFields = [];

        foreach ($criteria as $index => $criterion) {
            $field = is_array($criterion) ? ($criterion['field'] ?? null) : null;

            if ($field === null) {
                continue;
            }

            if (in_array($field, $seenFields, true)) {
                $validator->errors()->add("criteria.{$index}.field", 'This field is already used by another criterion.');

                continue;
            }

            $seenFields[] = $field;
        }
    }

    /**
     * @param  array<int, mixed>  $criteria
     */
    private function assertValueIdsExist(Validator $validator, array $criteria): void
    {
        $registry = app(WorkOrderCriterionFieldRegistry::class);

        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $field = $criterion['field'] ?? null;
            $valueId = $criterion['value_id'] ?? null;

            if (! is_string($field) || ! $registry->isAllowed($field) || ! is_numeric($valueId)) {
                continue;
            }

            if (! DB::table($registry->existsTable($field))->where('id', $valueId)->exists()) {
                $validator->errors()->add("criteria.{$index}.value_id", 'The selected value does not exist for this field.');
            }
        }
    }
}
