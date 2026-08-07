<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\Enums\LeadAssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/request-management/assign-operators (user directive
 * 2026-07-23, "come nei lead"): bulk-assign a Sede operativa and the GA2
 * "Operatore" to many requests at once, either to a single chosen operator
 * (`mode=single`) or load-balanced across the Sede's operators
 * (`mode=balanced`).
 *
 * LeadAssignmentMode is REUSED as-is rather than duplicated: it is the very
 * same two-mode contract the shared AssignOperatorsDialog submits (renaming it
 * would touch the leads/imports call sites, out of this scope).
 *
 * `request_ids` are Offerta (Quote) ids (spec 0086, D-2).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller:
 * the `request-management.update` gate plus the per-row D-3 scope), same
 * convention as UpdateRequestRequest.
 */
class AssignRequestOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller (permission + D-3 scope).
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
            'operational_site_id' => ['required', 'integer', Rule::exists('operational_sites', 'id')],
            'mode' => ['required', Rule::enum(LeadAssignmentMode::class)],
            'operator_id' => ['required_if:mode,single', 'integer', Rule::exists('users', 'id')],
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

    public function mode(): LeadAssignmentMode
    {
        return LeadAssignmentMode::from((string) $this->validated('mode'));
    }

    public function operatorId(): ?int
    {
        $value = $this->validated('operator_id');

        return $value === null ? null : (int) $value;
    }
}
