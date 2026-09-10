<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadAssignmentMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/leads/assign-operators (spec 0048): bulk-assign an
 * Operatore to many REAL leads at once, either to a single chosen operator
 * (`mode=single`) or load-balanced across the operators of each lead's own
 * Sede (`mode=balanced`, LeadOperatorDistributor). `operator_id` is required
 * only in `single` mode.
 *
 * `operational_site_id` is `prohibited` since spec 0113 (AC-021): the Sede is
 * derived server-side from the campaign of each lead (D-3) and is no longer
 * the caller's to choose. Rejecting the key outright — rather than ignoring
 * it — keeps a stale client from believing it still steers the assignment.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, per lead, via LeadPolicy — same convention as
 * StoreLeadRequest/UpdateLeadRequest).
 */
class AssignOperatorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via LeadPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')],
            'operational_site_id' => ['prohibited'],
            'mode' => ['required', Rule::enum(LeadAssignmentMode::class)],
            'operator_id' => ['required_if:mode,single', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The submitted lead ids, deduplicated.
     *
     * @return array<int, int>
     */
    public function leadIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('lead_ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
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
