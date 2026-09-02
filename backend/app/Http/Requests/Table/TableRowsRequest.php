<?php

namespace App\Http\Requests\Table;

use App\Http\Controllers\Abstract\BaseApiController;
use App\Services\Table\AdvancedFilterApplier;
use App\Tables\Quotes\OpportunityScopedTableDefinition;
use App\Tables\RequestManagement\RequestManagementScopedTableDefinition;
use App\Tables\TableDefinition;
use App\Tables\TableRegistry;
use App\Tables\WorkOrders\QuoteScopedTableDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates the AG Grid SSRM payload for POST /api/tables/{domain}/rows.
 *
 * The domain is unknown at boot, so the TableDefinition is resolved here from
 * the {domain} route segment. An UNKNOWN domain surfaces as 404 BEFORE
 * validation runs (TableRegistry::resolve throws ModelNotFoundException), never
 * a misleading 422. The whitelist (sortable/filterable column ids) is sourced
 * from the resolved definition — identical checks as the old users-specific
 * request, just definition-driven.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via the definition's viewAny). This request only guarantees the payload is
 * well-formed AND that every colId / filter key is within the server-side
 * whitelist. Any out-of-whitelist key yields a 422 and never reaches the query.
 */
class TableRowsRequest extends FormRequest
{
    /**
     * Max length of the global quick-search term (spec 0009). 255, aligned with
     * TableValuesRequest and the for-select requests: a shorter cap rejected the
     * paste of a long record name (product names run past 100 chars), and the
     * 422 surfaced in the grid as an opaque row of "ERR" cells.
     */
    public const int SEARCH_MAX_LENGTH = 255;

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
        $maxLimit = BaseApiController::MAX_LIMIT;
        $definition = $this->definition();
        $sortable = $definition->sortableColumnIds();

        return [
            'startRow' => ['required', 'integer', 'min:0'],
            'endRow' => ['required', 'integer', 'max:'.($this->intInput('startRow') + $maxLimit)],

            'sortModel' => ['sometimes', 'array'],
            'sortModel.*' => ['array'],
            'sortModel.*.colId' => ['required_with:sortModel.*', 'string', Rule::in($sortable)],
            'sortModel.*.sort' => ['required_with:sortModel.*', 'string', Rule::in(['asc', 'desc'])],

            'filterModel' => ['sometimes', 'array'],
            // Whitelist the filter keys: every key must be a filterable column.
            'filterModel.*' => ['array'],

            // Global quick-search term (spec 0009). The columns it spans are the
            // server-side allow-list (searchableColumnIds), never the raw input.
            'search' => ['sometimes', 'nullable', 'string', 'max:'.self::SEARCH_MAX_LENGTH],

            // Second-level, backend-driven advanced filters (spec 0032). Keys
            // and value shapes are whitelisted against the definition's
            // advancedFilters() catalogue in withValidator() below.
            'advancedFilters' => ['sometimes', 'nullable', 'array'],

            // Spec 0064 (spec 0084 dropped its `attr.*`-column effect):
            // scopes `request-management`'s ROWS to one product category
            // (D-2) — a no-op key for every other domain.
            'productCategoryId' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],

            // Spec 0067: scopes `quotes` to one Opportunity's Offerte — a
            // no-op key for every other domain (D-1: the allow-lists above
            // never depend on this scope, unlike productCategoryId).
            'opportunityId' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],

            // Spec 0095, D-8: scopes `work-orders` to one Quote's own
            // Commesse (the Contratto detail's tab) — a no-op key for every
            // other domain, mirroring `opportunityId` (AC-053: OMITTED by
            // every existing caller, so their payload stays byte-identical).
            'quoteId' => ['sometimes', 'nullable', 'integer', Rule::exists('quotes', 'id')],
        ];
    }

    /**
     * Cross-field / structural checks that Laravel rules can't express cleanly:
     *  - endRow strictly greater than startRow;
     *  - block size (endRow - startRow) within MAX_LIMIT;
     *  - every filterModel key within the filterable whitelist.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $startRow = $this->intInput('startRow');
            $endRow = $this->intInput('endRow');

            if ($endRow <= $startRow) {
                $validator->errors()->add('endRow', 'endRow must be greater than startRow.');
            }

            if (($endRow - $startRow) > BaseApiController::MAX_LIMIT) {
                $validator->errors()->add(
                    'endRow',
                    'The requested block exceeds the maximum of '.BaseApiController::MAX_LIMIT.' rows.'
                );
            }

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

    private function intInput(string $key): int
    {
        return (int) $this->input($key, 0);
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

            if ($definition instanceof RequestManagementScopedTableDefinition) {
                $definition->scopeToProductCategory($this->productCategoryIdInput());
            }

            if ($definition instanceof OpportunityScopedTableDefinition) {
                $definition->scopeToOpportunity($this->opportunityIdInput());
            }

            if ($definition instanceof QuoteScopedTableDefinition) {
                $definition->scopeToQuote($this->quoteIdInput());
            }

            $this->resolvedDefinition = $definition;
        }

        return $this->resolvedDefinition;
    }

    /**
     * The raw `productCategoryId` request input, coerced to int — read
     * directly (not via `validated()`, not yet available while `rules()`
     * itself is being built) so the allow-lists above reflect the SAME scope
     * `Rule::exists` will separately reject if it does not exist.
     */
    private function productCategoryIdInput(): ?int
    {
        $value = $this->input('productCategoryId');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The raw `opportunityId` request input, coerced to int (spec 0067),
     * mirroring `productCategoryIdInput()`.
     */
    private function opportunityIdInput(): ?int
    {
        $value = $this->input('opportunityId');

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * The raw `quoteId` request input, coerced to int (spec 0095), mirroring
     * `opportunityIdInput()`.
     */
    private function quoteIdInput(): ?int
    {
        $value = $this->input('quoteId');

        return is_numeric($value) ? (int) $value : null;
    }
}
