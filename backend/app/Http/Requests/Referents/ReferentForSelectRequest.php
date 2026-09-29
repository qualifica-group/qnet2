<?php

namespace App\Http\Requests\Referents;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/referents/for-select (ADR 0011, spec 0020),
 * mirroring SourceForSelectRequest.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('viewAny', Referent::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * `registry_id` (spec 0040 BR-4) is NOT part of the shared ForSelectQuery DTO
 * — it feeds the Opportunity form's registry-scoped referent/commercial/
 * reporter pickers only, so it is read directly off the request by the
 * controller and passed to the Service as a separate argument, mirroring
 * BusinessFunctionForSelectRequest's `exclude_descendants_of`.
 */
class ReferentForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the ReferentPolicy.
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
            'registry_id' => ['sometimes', 'integer', 'exists:registries,id'],
        ];
    }

    /**
     * The validated `registry_id` scope, or null when not submitted.
     */
    public function registryId(): ?int
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_key_exists('registry_id', $validated) ? (int) $validated['registry_id'] : null;
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
