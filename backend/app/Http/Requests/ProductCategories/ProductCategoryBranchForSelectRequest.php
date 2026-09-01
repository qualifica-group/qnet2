<?php

namespace App\Http\Requests\ProductCategories;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/product-category-branches/for-select
 * (spec 0092, ADR 0011), mirroring ProductCategoryForSelectRequest minus the
 * scoping params (`business_function_id`/`root_category_id`): the branch
 * picker has a single consumer, the quote-workflow criteria editor, which
 * only ever searches and paginates.
 *
 * Authorization is intentionally absent: option lists feed forms whose actor
 * may legitimately lack browse rights on the source module (ADR 0011, amended
 * 2026-07-31), so `auth:sanctum` is the only gate.
 */
class ProductCategoryBranchForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
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
