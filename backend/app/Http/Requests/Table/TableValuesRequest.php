<?php

namespace App\Http\Requests\Table;

use App\Tables\RequestManagement\AttributeScopedTableDefinition;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the payload for POST /api/tables/{domain}/values (Excel-like
 * distinct values for a single column).
 *
 * Mirrors TableRowsRequest: the domain is unknown at boot, so the
 * TableDefinition is resolved here from the {domain} route segment — an
 * UNKNOWN domain surfaces as 404 BEFORE validation runs, never a misleading
 * 422. `columnId` AND every `filterModel` key must be within the resolved
 * definition's filterable whitelist; anything outside it yields a 422 and
 * never reaches the query.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via the definition's viewAny), same convention as
 * TableRowsRequest.
 */
class TableValuesRequest extends FormRequest
{
    /**
     * Cap + default for the number of distinct values returned. Mirrors the
     * `limit` bound documented in spec 0004 (cap 200, default 200).
     */
    private const int MAX_LIMIT = 200;

    private const int DEFAULT_LIMIT = 200;

    private ?TableDefinition $resolvedDefinition = null;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the definition's viewAny.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $filterable = $this->definition()->filterableColumnIds();

        return [
            'columnId' => ['required', 'string', Rule::in($filterable)],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],

            'filterModel' => ['sometimes', 'array'],
            // Whitelist the filter keys: every key must be a filterable column.
            'filterModel.*' => ['array'],

            // Spec 0064: required to resolve an `attr.*` columnId for
            // `request-management` (`columnId` above is ALREADY checked
            // against this same scoped instance's filterableColumnIds() —
            // see definition() — so an attr.* columnId with this key absent
            // is rejected by Rule::in() without any extra logic, AC-014).
            'productCategoryId' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
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

            if (is_array($filterModel)) {
                $filterable = $this->definition()->filterableColumnIds();

                foreach (array_keys($filterModel) as $columnId) {
                    if (! in_array($columnId, $filterable, true)) {
                        $validator->errors()->add(
                            "filterModel.{$columnId}",
                            "Filtering is not allowed on column [{$columnId}]."
                        );
                    }
                }
            }
        });
    }

    /**
     * Validated payload with the `limit`/`filterModel` defaults applied.
     *
     * @return array{columnId: string, search: string|null, limit: int, filterModel: array<string, array<string, mixed>>, productCategoryId: int|null}
     */
    public function payload(): array
    {
        $validated = $this->validated();

        return [
            'columnId' => $validated['columnId'],
            'search' => $validated['search'] ?? null,
            'limit' => $validated['limit'] ?? self::DEFAULT_LIMIT,
            'filterModel' => $validated['filterModel'] ?? [],
            'productCategoryId' => isset($validated['productCategoryId']) ? (int) $validated['productCategoryId'] : null,
        ];
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
            $definition = app(TableRegistry::class)->resolve($domain);

            if ($definition instanceof AttributeScopedTableDefinition) {
                $definition->scopeToProductCategory($this->productCategoryIdInput());
            }

            $this->resolvedDefinition = $definition;
        }

        return $this->resolvedDefinition;
    }

    /**
     * The raw `productCategoryId` request input, coerced to int (read
     * directly, not via `validated()`, mirroring TableRowsRequest).
     */
    private function productCategoryIdInput(): ?int
    {
        $value = $this->input('productCategoryId');

        return is_numeric($value) ? (int) $value : null;
    }
}
