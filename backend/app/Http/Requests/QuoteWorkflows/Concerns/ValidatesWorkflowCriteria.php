<?php

declare(strict_types=1);

namespace App\Http\Requests\QuoteWorkflows\Concerns;

use App\DataObjects\QuoteWorkflows\CreateQuoteWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Enums\WorkflowStatusSystemKey;
use App\Models\QuoteWorkflow;
use App\Support\QuoteWorkflows\QuoteCriterionFieldRegistry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Shared validation for the `criteria`/`statuses` payload of the
 * quote-workflow write endpoints (spec 0047, moved onto the Offerta by spec
 * 0083 D-6): the shape rules (criteriaRules()/statusesRules()) are IDENTICAL
 * between store/update (only their "required vs sometimes" cardinality
 * differs); the cross-row invariants (no duplicate `field`, `value_id`
 * exists for its field, criteria-combination uniqueness — AC-008/AC-009) run
 * in validateCriteria(), called from each request's own withValidator().
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesWorkflowCriteria
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function criteriaRules(bool $required): array
    {
        // QuoteCriterionFieldRegistry needs the container (custom fields,
        // per-request memoized) — resolved via app() rather than constructor
        // injection, mirroring the existing FormRequest-concern precedent
        // (ValidatesFieldTypeDefinition -> CustomFieldEntityRegistry).
        $allowedFields = array_column(app(QuoteCriterionFieldRegistry::class)->allowedFields(), 'field');

        return [
            'criteria' => $required ? ['required', 'array', 'min:1'] : ['sometimes', 'array', 'min:1'],
            'criteria.*.field' => ['required', 'string', Rule::in($allowedFields)],
            'criteria.*.value_id' => ['required', 'integer'],
        ];
    }

    /**
     * @param  bool  $allowIds  update passes true (statuses.*.id identifies
     *                          an existing row); store passes false (a submitted status is
     *                          either a new custom row or one of the pinned system rows,
     *                          tagged by `system_key`).
     * @return array<string, array<int, mixed>>
     */
    protected function statusesRules(bool $allowIds): array
    {
        $rules = [
            'statuses' => ['sometimes', 'array'],
            'statuses.*.name' => ['required', 'string', 'max:191'],
            'statuses.*.description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'statuses.*.color' => ['nullable', 'string', 'max:32'],
            'statuses.*.group' => ['required', Rule::enum(WorkflowStatusGroup::class)],
            'statuses.*.requires_note' => ['sometimes', 'boolean'],
            // Create carries the pinned rows tagged open/closed_won/
            // closed_lost so the user can name them up front (AC-004); a custom
            // row is null/absent.
            'statuses.*.system_key' => ['sometimes', 'nullable', Rule::enum(WorkflowStatusSystemKey::class)],
        ];

        if ($allowIds) {
            $rules['statuses.*.id'] = ['sometimes', 'integer'];
        }

        return $rules;
    }

    /**
     * Runs every `criteria` cross-row check, skipping the signature-
     * uniqueness check (which needs a WELL-FORMED collection) when an
     * earlier check already reported an error.
     */
    protected function validateCriteria(Validator $validator, ?int $excludeWorkflowId): void
    {
        $criteria = $this->input('criteria');

        if (! is_array($criteria)) {
            return;
        }

        $this->assertDistinctFields($validator, $criteria);
        $this->assertValueIdsExist($validator, $criteria);

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $this->assertSignatureUnique($validator, $criteria, $excludeWorkflowId);
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
        $registry = app(QuoteCriterionFieldRegistry::class);

        foreach ($criteria as $index => $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $field = $criterion['field'] ?? null;
            $valueId = $criterion['value_id'] ?? null;

            if (! is_string($field) || ! $registry->isAllowed($field) || $valueId === null) {
                continue;
            }

            $exists = DB::table($registry->existsTable($field))->where('id', $valueId)->exists();

            if (! $exists) {
                $validator->errors()->add("criteria.{$index}.value_id", 'The selected value does not exist for this field.');
            }
        }
    }

    /**
     * @param  array<int, mixed>  $criteria
     */
    private function assertSignatureUnique(Validator $validator, array $criteria, ?int $excludeWorkflowId): void
    {
        $signature = CreateQuoteWorkflowData::computeSignature($criteria);

        $query = QuoteWorkflow::query()->where('criteria_signature', $signature);

        if ($excludeWorkflowId !== null) {
            $query->where('id', '!=', $excludeWorkflowId);
        }

        if ($query->exists()) {
            $validator->errors()->add('criteria', 'A workflow with this exact combination of criteria already exists.');
        }
    }
}
