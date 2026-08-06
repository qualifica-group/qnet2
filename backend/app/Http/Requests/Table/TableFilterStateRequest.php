<?php

namespace App\Http\Requests\Table;

use App\Services\Table\AdvancedFilterApplier;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validates the filter-state payload for POST /api/tables/{domain}/filters.
 *
 * Like TableRowsRequest, the domain is unknown at boot, so the definition is
 * resolved here from the {domain} route segment — an UNKNOWN domain surfaces as
 * 404 BEFORE validation (TableRegistry::resolve throws), never a misleading 422.
 *
 * SECURITY: every filterModel key must be a FILTERABLE column of the resolved
 * definition — the exact same allow-list the SSRM rows endpoint enforces
 * (TableRowsRequest::withValidator). Any out-of-whitelist key yields a 422 and
 * never reaches the store. An empty model is accepted and clears the saved state.
 *
 * Authorization stays in the controller via the definition's viewAny.
 */
class TableFilterStateRequest extends FormRequest
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
        return [
            // `present` (not `required`) so an empty {} is valid: it clears the
            // saved filters, mirroring an explicit reset.
            'filterModel' => ['present', 'array'],
            'filterModel.*' => ['array'],

            // Advanced filters (spec 0032) are independently OPTIONAL: absent
            // means "leave the persisted advanced filters untouched" (see
            // advancedFilters()); present (even `{}`) replaces them.
            'advancedFilters' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Whitelist the filter keys: every column id must be filterable in the
     * definition (identical to TableRowsRequest).
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

            $advancedFilters = $this->input('advancedFilters');

            if (is_array($advancedFilters)) {
                $catalog = array_column($this->definition()->advancedFilters(), null, 'name');
                $errors = app(AdvancedFilterApplier::class)->validate($catalog, $advancedFilters);

                foreach ($errors as $name => $message) {
                    $validator->errors()->add("advancedFilters.{$name}", $message);
                }
            }
        });
    }

    /**
     * The validated filterModel (empty array when none/cleared).
     *
     * @return array<string, mixed>
     */
    public function filterModel(): array
    {
        /** @var array<string, mixed> $model */
        $model = $this->validated('filterModel', []);

        return $model;
    }

    /**
     * The validated `advancedFilters` payload, or null when the key is ABSENT
     * from the request — the caller (TableFilterStateService::save()) reads
     * null as "leave the persisted advanced filters untouched", distinct from
     * an explicit empty `{}` (which clears them).
     *
     * @return array<string, mixed>|null
     */
    public function advancedFilters(): ?array
    {
        if (! $this->has('advancedFilters')) {
            return null;
        }

        /** @var array<string, mixed>|null $advancedFilters */
        $advancedFilters = $this->validated('advancedFilters');

        return $advancedFilters ?? [];
    }

    /**
     * Resolve the TableDefinition for the route's {domain}. Unknown domain →
     * ModelNotFoundException → 404 (before any validation runs). Resolved once.
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
