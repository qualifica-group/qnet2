<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/product-categories/for-select (ADR 0011),
 * mirroring SourceForSelectRequest. `business_function_id` (spec 0040
 * amendment rev.3, optional): scopes the results to categories whose
 * EFFECTIVE business function matches — additive/retrocompatible, absent by
 * default. `root_category_id` (spec 0077, optional): scopes the results to
 * the subtree of that branch root (INV-1) — same additive/retrocompatible
 * shape.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', ProductCategory::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 */
class ProductCategoryForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the ProductCategoryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $maxLimit = BaseApiController::MAX_LIMIT;

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'offset' => ['sometimes', 'integer', 'min:0'],
            'limit' => ['sometimes', 'integer', 'min:1', "max:{$maxLimit}"],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['integer'],
            'business_function_id' => ['sometimes', 'nullable', 'integer', 'exists:business_functions,id'],
            'root_category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
        ];
    }

    /**
     * The validated query as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): ForSelectQuery
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ForSelectQuery::fromValidated($validated);
    }
}
