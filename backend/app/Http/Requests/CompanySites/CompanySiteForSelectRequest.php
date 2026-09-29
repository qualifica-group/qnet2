<?php

namespace App\Http\Requests\CompanySites;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/company-sites/for-select (spec 0040, ADR
 * 0011), mirroring CompanyForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', CompanySite::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * `company_id` (spec 0040 BR-4) is NOT part of the shared ForSelectQuery DTO —
 * it feeds the Opportunity form's company-scoped site picker only, so it is
 * read directly off the request by the controller and passed to the Service
 * as a separate argument, mirroring BusinessFunctionForSelectRequest's
 * `exclude_descendants_of`.
 */
class CompanySiteForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the CompanySitePolicy.
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
            'include_total' => ['sometimes', 'boolean'],
            'company_id' => ['sometimes', 'integer', 'exists:companies,id'],
        ];
    }

    /**
     * The validated `company_id` scope, or null when not submitted.
     */
    public function companyId(): ?int
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_key_exists('company_id', $validated) ? (int) $validated['company_id'] : null;
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
