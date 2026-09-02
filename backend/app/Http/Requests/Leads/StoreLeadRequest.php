<?php

namespace App\Http\Requests\Leads;

use App\DataObjects\Leads\CreateLeadData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/leads (spec 0024, spec 0041 D-1).
 * `registry_id`/`campaign_id` are required (BR-1); the other relations are
 * optional, `notes` is a plain nullable text. Lead status is derived and is
 * not accepted in the write contract.
 *
 * `convert_to_opportunity` (spec 0044) is a request-level flag, not a Lead
 * column. Sede (`operational_site_id`) and Operatore (`operator_id`) stay
 * OPTIONAL even when it is true (user directive 2026-07-21, relaxing spec
 * 0044 AC-008/009): a Lead can be converted without them — the derived
 * Opportunity simply inherits a null supervisor (supervisor_id is DB-nullable).
 * The `opportunities.create` authorization for the flag stays in the
 * controller, alongside the plain `leads.create` check.
 *
 * `state_id` (Regione, spec 0047) is now a first-class USER input (directive
 * 2026-07-21): freely editable, auto-filled client-side from the chosen Sede
 * but overridable. When omitted, LeadService derives it from the Sede as a
 * fallback; a submitted value always wins.
 *
 * `products_of_interest` (spec 0094, D-5): OPTIONAL — unlike the opportunity
 * counterpart, a Lead is valid with zero products (AC-036), so this carries
 * no `min:1`. Coherence against the campaign's covered categories is
 * enforced service-side (App\Services\Leads\LeadProductInterestWriter), not
 * here.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Lead::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreLeadRequest extends FormRequest
{
    use EnforcesFieldPermissions;

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
            'registry_id' => ['required', 'integer', Rule::exists('registries', 'id')],
            'campaign_id' => ['required', 'integer', Rule::exists('campaigns', 'id')],
            'operational_site_id' => ['nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'operator_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'extra_fields' => ['nullable', 'array'],
            'extra_fields.*' => ['string'],
            'convert_to_opportunity' => ['nullable', 'boolean'],
            'products_of_interest' => ['sometimes', 'array'],
            'products_of_interest.*' => ['integer', Rule::exists('products', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'leads';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateLeadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateLeadData::fromValidated($validated);
    }
}
