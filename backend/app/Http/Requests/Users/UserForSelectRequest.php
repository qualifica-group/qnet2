<?php

namespace App\Http\Requests\Users;

use App\DataObjects\Shared\ForSelectQuery;
use App\Http\Controllers\Abstract\BaseApiController;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query for GET /api/users/for-select (ADR 0011).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller via
 * authorize('viewAny', User::class)). Pagination bounds mirror
 * BaseApiController::validateRequest (offset >= 0, 1 <= limit <= MAX_LIMIT).
 *
 * `operational_site_id` (spec 0048, ADDITIVE): restricts the list to users
 * whose employment profile points to that Sede — feeds the Lead form's
 * Sede-filtered Operatore select and the "Assegna operatori" popup. It rides
 * ForSelectQuery::operationalSiteId (fromValidated picks it up automatically),
 * consumed only by UserService::forSelect.
 *
 * `competence_category_ids` (spec 0110, ADDITIVE): the categories a selected
 * record requires — the list narrows to the operators competent for at least
 * one of them (INV-3), wildcards included (INV-4b). Absent or empty means no
 * filter at all, so every pre-0110 caller is unaffected. `exists:` on each id
 * because an unknown category must be a 422, never a silently empty picker.
 */
class UserForSelectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the UserPolicy.
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
            'operational_site_id' => ['sometimes', 'integer', 'exists:operational_sites,id'],
            'competence_category_ids' => ['sometimes', 'array'],
            'competence_category_ids.*' => ['integer', 'exists:product_categories,id'],
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
