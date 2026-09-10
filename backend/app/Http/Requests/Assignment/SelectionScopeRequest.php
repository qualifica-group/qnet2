<?php

namespace App\Http\Requests\Assignment;

use App\Enums\AssignmentDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates POST /api/assignment/selection-scope (spec 0113, renamed from
 * `required-categories` of spec 0110): the selection whose assignment scope —
 * required categories, shared Sede, campaigns — the operator picker filters on.
 *
 * Authorization is deliberately NOT here: the endpoint reuses the READ gate
 * of whichever domain it was asked about — a different gate per `domain` —
 * so it stays in the controller, where the domain has already been resolved.
 *
 * `import_run_id` is validated as a plain integer, never with `exists:`: the
 * contract answers 404 (not 422) for a run that does not exist or belongs to
 * another actor, and an `exists:` rule would both degrade that to a 422 and
 * confirm the existence of another actor's run.
 *
 * `select_all`/`row_ids` carry the same AG Grid semantics as
 * BulkAssignRequest — `row_ids` TARGET when `select_all` is false, EXCLUDE
 * when true — including its "row_ids required unless select_all" rule, so a
 * picker and the assignment it precedes can never read the selection
 * differently. Row ownership needs no check of its own: the rows are read
 * scoped to the run (ImportRunRowSelection), so a foreign id matches nothing.
 */
class SelectionScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $importRows = AssignmentDomain::ImportRows->value;
        $idsDomains = AssignmentDomain::Leads->value.','.AssignmentDomain::Quotes->value;

        return [
            'domain' => ['required', Rule::enum(AssignmentDomain::class)],
            'import_run_id' => ["required_if:domain,{$importRows}", 'integer'],
            'select_all' => ['nullable', 'boolean'],
            'row_ids' => ['sometimes', 'array'],
            'row_ids.*' => ['integer'],
            'ids' => ["required_if:domain,{$idsDomains}", 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('domain') !== AssignmentDomain::ImportRows->value) {
                return;
            }

            if ($this->selectAll() || $this->rowIds() !== []) {
                return;
            }

            $validator->errors()->add('row_ids', 'row_ids is required when select_all is false.');
        });
    }

    public function domain(): AssignmentDomain
    {
        return AssignmentDomain::from((string) $this->validated('domain'));
    }

    public function importRunId(): int
    {
        return (int) $this->input('import_run_id');
    }

    public function selectAll(): bool
    {
        return $this->boolean('select_all', false);
    }

    /**
     * @return array<int, int>
     */
    public function rowIds(): array
    {
        $rowIds = $this->input('row_ids', []);

        return is_array($rowIds) ? array_map('intval', $rowIds) : [];
    }

    /**
     * The selected lead / offer ids (`domain` = leads | quotes).
     *
     * @return array<int, int>
     */
    public function ids(): array
    {
        $ids = $this->input('ids', []);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }
}
