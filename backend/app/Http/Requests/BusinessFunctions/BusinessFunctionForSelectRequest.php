<?php

namespace App\Http\Requests\BusinessFunctions;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/business-functions/for-select (ADR 0011),
 * mirroring UserForSelectRequest/RoleForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', BusinessFunction::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * `exclude_descendants_of` (spec 0010 REV) is NOT part of the shared
 * ForSelectQuery DTO — it feeds the edit-mode parent picker only, so it is
 * read directly off the request by the controller and passed to the Service
 * as a separate argument, never widening the generic for-select contract.
 *
 * `include_inactive` (spec 0280 D-6) lifts the active filter for the
 * structural and search consumers (parent picker, report filters).
 */
class BusinessFunctionForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the BusinessFunctionPolicy.
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
            'exclude_descendants_of' => ['sometimes', 'integer', 'exists:business_functions,id'],
            'include_inactive' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated `exclude_descendants_of` param, or null when not
     * submitted.
     */
    public function excludeDescendantsOf(): ?int
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_key_exists('exclude_descendants_of', $validated)
            ? (int) $validated['exclude_descendants_of']
            : null;
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
