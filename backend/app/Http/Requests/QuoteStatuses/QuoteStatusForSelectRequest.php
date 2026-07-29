<?php

namespace App\Http\Requests\QuoteStatuses;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/quote-statuses/for-select (ADR 0011): a
 * plain clone of OpportunityStatusForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', QuoteStatus::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 */
class QuoteStatusForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the QuoteStatusPolicy.
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
