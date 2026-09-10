<?php

namespace App\Http\Requests\Table;

use App\Services\Table\AdvancedFilterApplier;
use App\Tables\RequestManagement\RequestManagementScopedTableDefinition;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
 * `product_category_id` is OPTIONAL and never touches what is persisted (saved
 * filters are per-domain, not per-tab — D-4): it only tells the controller
 * which category shape to rebuild the RESPONSE config on, so the client can
 * refresh the cache entry it actually reads (spec 0064).
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

            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The category tab the client saved from, so the response config carries
     * that tab's `attr.*` columns instead of the unscoped shape. Null = the
     * "Tutte" tab, and every other domain, which never sends it. Independent
     * of the allow-list widening below: WHICH ids may be persisted is the
     * union (D-4), WHICH columns the response shows is this one tab.
     */
    public function productCategoryId(): ?int
    {
        $value = $this->validated('product_category_id');

        return $value === null ? null : (int) $value;
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
            $definition = app(TableRegistry::class)->resolve($domain);

            // Spec 0064, D-4: saved filters are per-domain, not per-tab — the
            // allow-list must accept the UNION of every category's `attr.*`
            // ids regardless of which tab was open when the client saved.
            if ($definition instanceof RequestManagementScopedTableDefinition) {
                $definition->scopeToAllProductCategories();
            }

            $this->resolvedDefinition = $definition;
        }

        return $this->resolvedDefinition;
    }
}
