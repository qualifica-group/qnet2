<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/request-management/transfer (spec 0079): transfers one
 * or many requests to another Sede operativa, assigning that site's own GA2
 * "Operatore" in the same call. Modelled on AssignRequestOperatorsRequest,
 * with ONE deviation: `operator_id` is REQUIRED (not `required_if:mode,...`)
 * — decision utente 2026-08-04 forces the dialog to Sede + Operatore only,
 * so a transfer with no destination operator does not exist by definition.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller: the double `update` + `transferContact` gate, plus the
 * per-row D-3 scope), same convention as AssignRequestOperatorsRequest.
 */
class TransferRequestsRequest extends FormRequest
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
            'request_ids.*' => ['integer', Rule::exists('opportunities', 'id')],
            'operational_site_id' => ['required', 'integer', Rule::exists('operational_sites', 'id')],
            'operator_id' => ['required', 'integer', Rule::exists('users', 'id')],
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

    public function operationalSiteId(): int
    {
        return (int) $this->validated('operational_site_id');
    }

    public function operatorId(): int
    {
        return (int) $this->validated('operator_id');
    }
}
