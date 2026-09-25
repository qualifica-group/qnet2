<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\LeadAssignmentMode;
use Illuminate\Validation\Rule;

/**
 * The `operators_by_site` field (spec 0168): `mode=balanced`'s per-Sede
 * operator selection, shared VERBATIM by the three bulk assignment
 * FormRequests (Leads/RequestManagement/Import) so the rule can never drift
 * between them (constraints: "Regole di validazione `operators_by_site`
 * definite UNA volta e riusate dai 3 FormRequest").
 *
 * `prohibited` outside `mode=balanced` mirrors `operational_site_id`'s own
 * rule (spec 0113): a client still submitting it while asking for `single`
 * is working against a contract that does not apply, and must be told (422)
 * rather than silently ignored. Absent altogether, the field validates to
 * nothing and every caller's current behaviour is unchanged (retro-compat).
 *
 * `operational_site_id` distinct across entries, `operator_ids` non-empty
 * and distinct WITHIN an entry — an operator sent but not a candidate of any
 * record at that Sede is not a validation error (rules): the restriction
 * (OperatorsBySitePoolRestriction) silently drops it via intersection.
 */
trait HasOperatorsBySiteRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function operatorsBySiteRules(): array
    {
        return [
            'operators_by_site' => [
                Rule::prohibitedIf(fn (): bool => $this->input('mode') !== LeadAssignmentMode::Balanced->value),
                'array',
            ],
            'operators_by_site.*.operational_site_id' => [
                'required', 'integer', 'distinct', Rule::exists('operational_sites', 'id'),
            ],
            'operators_by_site.*.operator_ids' => ['required', 'array', 'min:1'],
            'operators_by_site.*.operator_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    /**
     * The submitted entries, normalized to int ids — null when the field was
     * not submitted at all, the signal every caller reads as "unrestricted,
     * current behaviour" (spec 0168 rules).
     *
     * @return array<int, array{operational_site_id: int, operator_ids: array<int, int>}>|null
     */
    public function operatorsBySite(): ?array
    {
        if (! $this->has('operators_by_site')) {
            return null;
        }

        /** @var array<int, array{operational_site_id: mixed, operator_ids: array<int, mixed>}> $entries */
        $entries = $this->validated('operators_by_site', []);

        return array_map(static fn (array $entry): array => [
            'operational_site_id' => (int) $entry['operational_site_id'],
            'operator_ids' => array_values(array_unique(array_map(intval(...), $entry['operator_ids']))),
        ], $entries);
    }
}
