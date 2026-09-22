<?php

namespace App\Http\Requests\Export;

use App\Http\Requests\Table\TableRowsRequest;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates POST /api/exports/{domain} (spec 0014).
 *
 * The domain is unknown at boot, so the TableDefinition is resolved here from
 * the {domain} route segment — an UNKNOWN domain surfaces as 404 BEFORE
 * validation runs (TableRegistry::resolve throws ModelNotFoundException),
 * mirroring TableRowsRequest. `columns[].colId`/`sortModel[].colId`/
 * `filterModel` keys are allow-listed against the resolved definition; the
 * client-supplied `header` is validated only for shape (string, ≤255) — it is
 * used SOLELY as a file column label, never in any query.
 *
 * Authorization is intentionally NOT handled here (the controller enforces
 * the `{domain}.export` ability via the definition's modelClass(), which this
 * FormRequest does not see resolved before the controller runs).
 *
 * `opportunityId` (spec 0067, D-5) scopes the `quotes` export to one
 * Opportunity's Offerte — a no-op key for every other domain, mirroring
 * `TableRowsRequest`. `quoteId` (spec 0095, D-8) is the same mechanism for
 * `work-orders`, scoped to one Quote's own Commesse. Unlike
 * `columns`/`sortModel`/`filterModel`, neither is used to scope
 * `definition()` here: the allow-lists they feed (columnIds,
 * sortableColumnIds, filterableColumnIds) are identical scoped or not (D-1),
 * so there is nothing for the scope to widen or narrow at validation time.
 * The value only starts mattering once ExportController::store() freezes it
 * into ExportRun::state for GenerateExportJob to re-apply asynchronously.
 */
class CreateExportRequest extends FormRequest
{
    private ?TableDefinition $resolvedDefinition = null;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $definition = $this->definition();

        return [
            'format' => ['required', 'string', Rule::in(config('exports.formats'))],

            'columns' => ['required', 'array', 'min:1'],
            'columns.*.colId' => ['required', 'string', Rule::in($this->columnIds($definition))],
            'columns.*.header' => ['required', 'string', 'max:255'],

            'sortModel' => ['sometimes', 'array'],
            'sortModel.*' => ['array'],
            'sortModel.*.colId' => ['required_with:sortModel.*', 'string', Rule::in($definition->sortableColumnIds())],
            'sortModel.*.sort' => ['required_with:sortModel.*', 'string', Rule::in(['asc', 'desc'])],

            'filterModel' => ['sometimes', 'array'],
            'filterModel.*' => ['array'],

            // Same cap as the rows endpoint: the export carries the very term
            // the grid is filtered by, so the two must never diverge.
            'search' => ['sometimes', 'nullable', 'string', 'max:'.TableRowsRequest::SEARCH_MAX_LENGTH],

            // Spec 0067, D-5: scopes a `quotes` export to one Opportunity's
            // Offerte — a no-op key for every other domain.
            'opportunityId' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],

            // Spec 0095, D-8: scopes a `work-orders` export to one Quote's
            // own Commesse — a no-op key for every other domain.
            'quoteId' => ['sometimes', 'nullable', 'integer', Rule::exists('quotes', 'id')],
        ];
    }

    /**
     * Cross-field check Laravel rules can't express cleanly: every
     * filterModel key must be within the filterable whitelist.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $filterModel = $this->input('filterModel');

            if (! is_array($filterModel)) {
                return;
            }

            $filterable = $this->definition()->filterableColumnIds();

            foreach (array_keys($filterModel) as $columnId) {
                if (! in_array($columnId, $filterable, true)) {
                    $validator->errors()->add(
                        "filterModel.{$columnId}",
                        "Filtering is not allowed on column [{$columnId}]."
                    );
                }
            }
        });
    }

    /**
     * Every declared column id (the allow-list for `columns[].colId` — every
     * VISIBLE-or-not column may be exported, unlike sortable/filterable).
     *
     * @return array<int, string>
     */
    private function columnIds(TableDefinition $definition): array
    {
        return array_column($definition->columns(), 'id');
    }

    /**
     * Resolve the TableDefinition for the route's {domain}. Unknown domain →
     * ModelNotFoundException → 404 (before any validation runs). Resolved once
     * per request.
     */
    private function definition(): TableDefinition
    {
        if ($this->resolvedDefinition === null) {
            $domain = (string) $this->route('domain');
            $this->resolvedDefinition = app(TableRegistry::class)->resolve($domain);
        }

        return $this->resolvedDefinition;
    }
}
