<?php

namespace App\Http\Requests\Opportunities;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/opportunities/for-select (ADR 0011),
 * mirroring LeadForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('viewAny', Opportunity::class)). Pagination
 * bounds mirror BaseApiController::validateRequest (offset >= 0, 1 <= limit
 * <= MAX_LIMIT).
 *
 * `registry_id` (spec 0122, D-5): ADDITIVE, optional client filter for the
 * segnatempo form's cascading select — retrocompatible, no behaviour change
 * when absent (AC-026).
 */
class OpportunityForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityPolicy.
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
            'registry_id' => ['sometimes', 'nullable', 'integer', Rule::exists('registries', 'id')],
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
