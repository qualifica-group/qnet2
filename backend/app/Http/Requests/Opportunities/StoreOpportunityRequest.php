<?php

namespace App\Http\Requests\Opportunities;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Opportunities\LeadOpportunityDefaults;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesRewards;
use App\Http\Requests\Concerns\ValidatesWorkflowStatus;
use App\Models\Lead;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/opportunities (spec 0040).
 * `registry_id` is required UNLESS `lead_id` derives
 * it (BR-1). The 2 BR-1-derivable fields (source_id/registry_id) become
 * `prohibited` when `lead_id` derives a non-null value for them —
 * LeadOpportunityDefaultsResolver is the single source of truth for which
 * ones, shared verbatim with OpportunityService's write-side derivation and
 * the GET /api/leads/{lead}/opportunity-defaults prefill. `referent_id` is
 * NOT derivable (spec 0041 D-1/D-3): it stays a plain, always-editable field
 * scoped to the chosen registry (BR-4, spec 0040).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Opportunity::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REPLACED
 * by `product_lines` (ValidatesProductLines) — no longer BR-1-derivable/
 * lockable scalars. User directive 2026-07-17: `product_lines` is REQUIRED
 * (at least one {business_function_id, product_category_id} row) to create;
 * `company_id`/`company_site_id` are REMOVED entirely. Spec 0056: unlike
 * those two, `operational_site_id` is reintroduced as a plain, optional FK —
 * never BR-1-derivable/locked, no forcing from any other entity.
 * `opportunity_status_id` (spec 0082) is `prohibited`: the Opportunity's
 * status is COMPUTED from its quotes, never submitted. `supervisor_id` is
 * NULLABLE (directive 2026-07-21, relaxing spec 0044): it derives from the
 * lead's Operatore, which may be empty, so an opportunity created from a lead
 * without one carries no supervisor — the DB column has always been nullable.
 *
 * Spec 0057, D-5: `name` is REMOVED entirely — no longer a client input.
 * OpportunityService derives it as `OPP_{id}` after the insert.
 */
class StoreOpportunityRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;
    use ValidatesProductLines;
    use ValidatesRewards;
    use ValidatesWorkflowStatus;

    private ?LeadOpportunityDefaults $leadDefaultsCache = null;

    private bool $leadDefaultsResolved = false;

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
        $locked = $this->leadDefaults()?->lockedFields ?? [];

        return array_merge([
            'registry_id' => $this->derivableRule($locked, 'registry_id', required: true, table: 'registries'),
            'referent_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'source_id' => $this->derivableRule($locked, 'source_id', required: false, table: 'sources'),
            // Spec 0056: a plain, optional relation — never derivable/locked.
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'opportunity_status_id' => ['prohibited'],
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id'), Rule::unique('opportunities', 'lead_id')],
            'start_date' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'success_probability' => ['nullable', 'integer', 'between:0,100'],
            // "Note generali" (user directive 2026-07-27): free text, same
            // 5000-char ceiling as the lead `notes` it is inherited from.
            'general_notes' => ['nullable', 'string', 'max:5000'],
            // User directive 2026-08-05: the dynamic "Informazioni aggiuntive"
            // map, exactly as the request-management channels accept it. The
            // per-code deep validation (applicability/type/required, spec 0049
            // D-4) is NOT duplicated here: it runs in
            // RequestAttributeValueWriter, the single place that also resolves
            // the applicable set and merges the map, and surfaces as the same
            // 422 keyed `attribute_values.<code>`.
            'attribute_values' => ['sometimes', 'array'],
            // spec 0047: state_id (Regione, D1) is editable on a standalone
            // create, overwritten by BR-1 derivation when lead_id derives
            // one. opportunity_workflow_status_id is an OPTIONAL override
            // (AC-015/017); its set-membership is checked in withValidator.
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'opportunity_workflow_status_id' => ['nullable', 'integer', Rule::exists('opportunity_workflow_statuses', 'id')],
            // "Prodotti di interesse": MANDATORY (user directive 2026-07-23),
            // mirroring `product_lines` — at least one product to create, and
            // the whole collection is replaced when submitted. A product
            // outside the submitted `product_lines` categories is REFUSED
            // (user directive 2026-08-05): the coherence rule is checked by
            // OpportunityProductInterestWriter, once the lines are persisted,
            // so it cannot be expressed as a rule here.
            'products_of_interest' => ['required', 'array', 'min:1'],
            'products_of_interest.*' => ['integer', Rule::exists('products', 'id')],
        ], $this->managerSlotsRules(), $this->productLinesRules(required: true), $this->rewardsRules());
    }

    /**
     * One of the 2 BR-1-derivable fields: `prohibited` when $locked derives a
     * value for it, else the plain (required or nullable) relation rule.
     *
     * @param  array<int, string>  $locked
     * @return array<int, mixed>
     */
    private function derivableRule(array $locked, string $field, bool $required, string $table): array
    {
        if (in_array($field, $locked, true)) {
            return ['prohibited'];
        }

        return $required
            ? ['required', 'integer', Rule::exists($table, 'id')]
            : ['nullable', 'integer', Rule::exists($table, 'id')];
    }

    /**
     * The submitted `lead_id`'s BR-1 defaults, memoized (rules() only needs
     * to resolve it once). Null when no lead_id was submitted or it does not
     * exist — the `exists` rule on lead_id itself surfaces that as its own
     * 422, so every other field falls back to its standalone rule.
     */
    private function leadDefaults(): ?LeadOpportunityDefaults
    {
        if ($this->leadDefaultsResolved) {
            return $this->leadDefaultsCache;
        }

        $leadId = $this->input('lead_id');
        $lead = is_numeric($leadId) ? Lead::find((int) $leadId) : null;

        $this->leadDefaultsCache = $lead !== null
            ? app(LeadOpportunityDefaultsResolver::class)->resolve($lead)
            : null;
        $this->leadDefaultsResolved = true;

        return $this->leadDefaultsCache;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateManagerSlots($validator);
            $this->validateProductLines($validator);
            $this->validateRewards($validator, null);
            $this->enforceFieldPermissions($validator);
            $this->validateWorkflowStatus($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunities';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateOpportunityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateOpportunityData::fromValidated($validated);
    }
}
