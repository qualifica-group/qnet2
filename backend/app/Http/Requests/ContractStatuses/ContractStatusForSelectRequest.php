<?php

declare(strict_types=1);

namespace App\Http\Requests\ContractStatuses;

use App\DataObjects\Shared\ForSelectQuery;
use App\Enums\ContractStatusGroup;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query for GET /api/contract-statuses/for-select (ADR 0011,
 * spec 0072).
 *
 * Authorization is intentionally NOT handled here: NO gate beyond
 * `auth:sanctum` (ADR 0011, amended 2026-07-31 — AC-028). Pagination bounds
 * mirror BaseApiController::validateRequest (offset >= 0, 1 <= limit <=
 * MAX_LIMIT).
 */
class ContractStatusForSelectRequest extends FormRequest
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
            'status_groups' => ['sometimes', 'array'],
            'status_groups.*' => [Rule::enum(ContractStatusGroup::class)],
            // Lifts the default `is_active = true` filter. Needed by the shared
            // reorder sheet ONLY: it seeds itself from this endpoint, while
            // StatusOrderManager validates `ordered_ids` against every custom
            // row regardless of `is_active` — so without this, deactivating one
            // custom status made every drag fail with 422 "none missing".
            'include_inactive' => ['sometimes', 'boolean'],
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
