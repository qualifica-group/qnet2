<?php

namespace App\Http\Requests\Opportunities;

use App\DataObjects\Opportunities\UpdateOpportunityData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/opportunities/{opportunity}
 * (spec 0040). Every field is `sometimes` (partial PATCH) —
 * `opportunity_status_id` (spec 0082) is ALWAYS `prohibited`: the status is
 * COMPUTED from the quotes, never submitted (spec 0083, D-2: the former
 * workflow-status override field is GONE too — no working-state override
 * exists any more on the Opportunity).
 * `lead_id` is ALWAYS `prohibited` (BR-2,
 * immutable once set). When $opportunity carries a `lead_id`, its 2
 * BR-1-derivable fields are re-resolved against the CURRENT lead/campaign
 * state (LeadOpportunityDefaultsResolver — same source as create): a
 * submission of one of them must equal that current derived value
 * (`Rule::in`) — a DIFFERENT value 422s, the SAME value is a no-op (BR-2).
 * `source_id` stays optional even unlocked. An opportunity with no lead has
 * none of these fields locked. `referent_id` is NOT derivable (spec 0041
 * D-1/D-3): it stays a plain, always-editable field scoped to the chosen
 * registry (BR-4, spec 0040).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $opportunity)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $opportunity.
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REPLACED
 * by `product_lines` (ValidatesProductLines) — no longer BR-1-derivable/
 * lockable scalars, a full-replace sync like `manager_slots`. User directive
 * 2026-07-17: `product_lines` may be OMITTED (partial PATCH, rows untouched)
 * but may NOT be cleared to `[]` (`min:1`) — an opportunity always keeps at
 * least one {business_function_id, product_category_id} row.
 * `company_id`/`company_site_id` are REMOVED entirely. Spec 0056: unlike
 * those two, `operational_site_id` is reintroduced as a plain, optional,
 * `sometimes|nullable` FK, clearable to null like every other unlocked
 * scalar (AC-004).
 *
 * Spec 0057, D-5: `name` is REMOVED entirely — immutable once derived at
 * create (`OPP_{id}`), never part of a PATCH payload.
 */
class UpdateOpportunityRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;
    use ValidatesProductLines;
    use ValidatesRewards;

    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Opportunity $opportunity */
        $opportunity = $this->route('opportunity');
        $locked = $this->currentLockedValues($opportunity);

        return array_merge([
            'registry_id' => $this->lockableRule($locked, 'registry_id', 'registries'),
            'referent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'commercial_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['sometimes', 'nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'source_id' => $this->lockableRule($locked, 'source_id', 'sources'),
            // Spec 0056: a plain, optional relation — never derivable/locked.
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'opportunity_status_id' => ['prohibited'],
            'lead_id' => ['prohibited'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'success_probability' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            // "Note generali" (user directive 2026-07-27): free text, same
            // 5000-char ceiling as the lead `notes` it is inherited from.
            'general_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            // spec 0047: state_id (Regione, D1) is freely editable on the
            // standalone opportunity.
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            // "Prodotti di interesse": MANDATORY (user directive 2026-07-23),
            // mirroring `product_lines`' own partial-PATCH shape — the key may
            // be omitted (untouched), but never cleared to `[]`. A product
            // outside the opportunity's product-line categories is REFUSED
            // (user directive 2026-08-05): the coherence rule needs the
            // record's persisted lines, so it is checked service-side
            // (OpportunityProductInterestWriter), not here.
            'products_of_interest' => ['sometimes', 'array', 'min:1'],
            'products_of_interest.*' => ['integer', Rule::exists('products', 'id')],
        ], $this->managerSlotsRules(), $this->productLinesRules(required: false), $this->rewardsRules());
    }

    /**
     * One of the 2 BR-1-derivable fields: when $locked carries a value for
     * it, the submission must match EXACTLY (`Rule::in`, BR-2); otherwise the
     * plain relation rule applies — `required` only for `registry_id`'s own
     * unlocked case, `nullable` for `source_id` (never forced on an unlocked,
     * previously-empty field).
     *
     * @param  array<string, int>  $locked
     * @return array<int, mixed>
     */
    private function lockableRule(array $locked, string $field, string $table, bool $required = false): array
    {
        if (array_key_exists($field, $locked)) {
            return ['sometimes', 'integer', Rule::in([$locked[$field]])];
        }

        return $required
            ? ['sometimes', 'required', 'integer', Rule::exists($table, 'id')]
            : ['sometimes', 'nullable', 'integer', Rule::exists($table, 'id')];
    }

    /**
     * $opportunity's currently-locked field values (BR-2), re-resolved
     * against the CURRENT lead/campaign state — empty when the opportunity
     * has no lead (or it has since been removed, defence in depth).
     *
     * @return array<string, int>
     */
    private function currentLockedValues(Opportunity $opportunity): array
    {
        if ($opportunity->lead_id === null) {
            return [];
        }

        $lead = Lead::find($opportunity->lead_id);

        if ($lead === null) {
            return [];
        }

        $defaults = app(LeadOpportunityDefaultsResolver::class)->resolve($lead);

        return Arr::only($defaults->values, $defaults->lockedFields);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Opportunity $opportunity */
            $opportunity = $this->route('opportunity');

            $this->validateManagerSlots($validator);
            $this->validateProductLines($validator);
            $this->validateRewards($validator, $opportunity);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunities';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Model $opportunity */
        $opportunity = $this->route('opportunity');

        return $opportunity;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateOpportunityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateOpportunityData::fromValidated($validated);
    }
}
