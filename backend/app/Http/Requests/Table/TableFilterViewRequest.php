<?php

namespace App\Http\Requests\Table;

use App\Enums\FilterViewVisibility;
use App\Services\Table\AdvancedFilterApplier;
use App\Services\Table\CustomFilterRuleValidator;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the payload for POST /api/tables/{domain}/filter-views (store) and
 * PUT /api/tables/{domain}/filter-views/{filterView} (update) — spec 0007.
 *
 * Like TableRowsRequest/TableFilterStateRequest, the domain is unknown at boot,
 * so the definition is resolved here from the {domain} route segment: an
 * UNKNOWN domain surfaces as 404 BEFORE validation, never a misleading 422.
 *
 * SECURITY: every `filters` key must be a FILTERABLE column of the resolved
 * definition — the exact same allow-list TableRowsRequest/TableFilterStateRequest
 * enforce. Any out-of-whitelist key yields a 422 and never reaches the store, so
 * a saved view can never widen the SSRM filter allow-list.
 *
 * Authorization is intentionally NOT handled here: list/create stay gated by
 * the definition's viewAny (controller), update/delete by TableFilterViewPolicy
 * (controller).
 */
class TableFilterViewRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('table_filter_views', 'name')
                    ->where(fn ($query) => $query
                        ->where('user_id', $this->user()?->id)
                        ->where('domain', $this->route('domain')))
                    ->ignore($this->route('filterView')),
            ],
            'visibility' => ['required', Rule::in(FilterViewVisibility::values())],
            // `present` (not `required`): an empty {} is a valid, empty view.
            'filters' => ['present', 'array'],
            'filters.*' => ['array'],

            // Advanced filters (spec 0032) are OPTIONAL for backward compat with
            // a view saved/updated before this field existed; absent defaults to
            // `{}` (advancedFiltersInput()).
            'advancedFilters' => ['sometimes', 'nullable', 'array'],

            // Custom filter rules (spec 0158): {and: Rule[], or: Rule[]} | null.
            // When present (non-null) the saved view is a "custom filter" view:
            // `filters`/`advancedFilters` are saved empty regardless of what is
            // submitted (TableFilterViewService).
            'rules' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Whitelist the filter keys: every column id must be filterable in the
     * definition (identical allow-list as TableRowsRequest/TableFilterStateRequest).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $filters = $this->input('filters');

            if (! is_array($filters)) {
                return;
            }

            $filterable = $this->definition()->filterableColumnIds();

            foreach (array_keys($filters) as $columnId) {
                if (! in_array($columnId, $filterable, true)) {
                    $validator->errors()->add(
                        "filters.{$columnId}",
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

            $rules = $this->input('rules');

            if ($rules !== null) {
                $errors = app(CustomFilterRuleValidator::class)->validate($this->definition(), $rules);

                foreach ($errors as $key => $message) {
                    $validator->errors()->add($key === '' ? 'rules' : "rules.{$key}", $message);
                }
            }
        });
    }

    public function nameInput(): string
    {
        return (string) $this->validated('name');
    }

    public function visibilityInput(): FilterViewVisibility
    {
        return FilterViewVisibility::from($this->validated('visibility'));
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersInput(): array
    {
        /** @var array<string, mixed> $filters */
        $filters = $this->validated('filters', []);

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    public function advancedFiltersInput(): array
    {
        /** @var array<string, mixed>|null $advancedFilters */
        $advancedFilters = $this->validated('advancedFilters');

        return $advancedFilters ?? [];
    }

    /**
     * @return array{and?: array<int, mixed>, or?: array<int, mixed>}|null
     */
    public function rulesInput(): ?array
    {
        /** @var array<string, mixed>|null $rules */
        $rules = $this->validated('rules');

        return $rules;
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
