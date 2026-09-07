<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/request-management/assign-manager-ga3 (spec 0104,
 * direttiva utente 2026-09-07): bulk-assign the GA3 slot — the "Tutor" of the
 * committente's vocabulary, named by the scoped category's
 * `manager_labels[3]` — to many Offerte at once.
 *
 * NO Sede in this contract, deliberately (D-1): only the GA2 Operatore slot
 * is bound to the Sede operativa, so this action has neither an
 * `operational_site_id` nor a `mode` — one chosen user goes onto every
 * selected row, and there is no site-scoped pool to balance across.
 *
 * `manager_ga3_id` is `present` and NULLABLE (D-2): the key must be sent, but
 * `null` is a legitimate value that CLEARS the slot on the whole batch —
 * `RequestOperatorWriter::applyGa3()` already accepts it. `nullable` alone
 * would let a missing key through as "clear", which is exactly the silent
 * destructive default this rule pair avoids.
 *
 * `request_ids` are Offerta (Quote) ids (spec 0086, D-2).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller: the `request-management.update` + `.assignManagerGa3` gates
 * plus the per-row D-3 scope), same convention as
 * AssignRequestOperatorsRequest.
 */
class AssignRequestManagerGa3Request extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller (permissions + D-3 scope).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'request_ids' => ['required', 'array', 'min:1'],
            'request_ids.*' => ['integer', Rule::exists('quotes', 'id')],
            'manager_ga3_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The submitted request ids, deduplicated.
     *
     * @return array<int, int>
     */
    public function requestIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('request_ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function managerGa3Id(): ?int
    {
        $value = $this->validated('manager_ga3_id');

        return $value === null ? null : (int) $value;
    }
}
