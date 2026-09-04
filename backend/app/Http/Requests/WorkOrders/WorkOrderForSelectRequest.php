<?php

namespace App\Http\Requests\WorkOrders;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/work-orders/for-select (ADR 0011),
 * mirroring OpportunityForSelectRequest. Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * No resource permission gate (ADR 0011, amended 2026-07-31): option lists
 * feed forms whose actor may legitimately lack browse rights on the source
 * module. The rows are nonetheless restricted by WorkOrderVisibilityScope
 * inside the Service (spec 0096) — that is a security boundary, not a browse
 * convenience.
 */
class WorkOrderForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // No resource gate on a for-select endpoint (ADR 0011).
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
