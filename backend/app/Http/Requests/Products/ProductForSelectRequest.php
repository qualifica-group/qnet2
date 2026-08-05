<?php

namespace App\Http\Requests\Products;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/products/for-select (ADR 0011), mirroring
 * ProductCategoryForSelectRequest. `category_ids` (user directive
 * 2026-07-22, optional): scopes the results to the products of those exact
 * categories — the "prodotti di interesse" picker always sends the categories
 * of the record's product lines; the quotes offer tab omits the key when the
 * operator explicitly unlocks the whole catalogue.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', Product::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 */
class ProductForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the ProductPolicy.
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
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:product_categories,id'],
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
