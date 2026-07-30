<?php

namespace App\Http\Requests\Leads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/leads/convert-to-opportunities (spec 0071): convert many
 * existing leads into their derived Opportunity in one synchronous,
 * all-or-nothing call. The batch size is capped by
 * `config('leads.bulk_conversion_max')` — the conversion runs inside a single
 * transaction, so an unbounded selection would risk the request timeout.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller:
 * OpportunityPolicy::create plus a per-lead LeadPolicy::view check — same
 * convention as StoreLeadRequest/AssignOperatorsRequest).
 */
class BulkConvertLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityPolicy/LeadPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:'.$this->maxBatchSize()],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')],
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

    private function maxBatchSize(): int
    {
        return (int) config('leads.bulk_conversion_max');
    }
}
