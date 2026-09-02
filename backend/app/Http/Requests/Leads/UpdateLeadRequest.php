<?php

namespace App\Http\Requests\Leads;

use App\DataObjects\Leads\UpdateLeadData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/leads/{lead} (spec 0024, spec 0041
 * D-1). Every field is `sometimes` (partial PATCH); `registry_id`/
 * `campaign_id`, IF submitted, cannot be null (BR-1 — mandatory fields never
 * accept an empty value once touched). Lead status is derived and is not
 * accepted in the write contract.
 *
 * `products_of_interest` (spec 0094, D-5): `sometimes|array`, no `min:1` —
 * `[]` is a valid submission that AZZERA the collection (AC-033). Coherence
 * against the campaign's covered categories, and the AC-034 guard on a
 * `campaign_id` change that would orphan a persisted product, are both
 * enforced service-side (LeadService/LeadProductInterestWriter), not here.
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('update', $lead)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * on this specific $lead.
 */
class UpdateLeadRequest extends FormRequest
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
            'registry_id' => ['sometimes', 'required', 'integer', Rule::exists('registries', 'id')],
            'campaign_id' => ['sometimes', 'required', 'integer', Rule::exists('campaigns', 'id')],
            'operational_site_id' => ['sometimes', 'nullable', 'integer', Rule::exists('operational_sites', 'id')],
            'source_id' => ['sometimes', 'nullable', 'integer', Rule::exists('sources', 'id')],
            'operator_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            // spec 0047 / directive 2026-07-21: Regione is now a user input,
            // editable and auto-filled client-side from the Sede. A submitted
            // value wins; if only the Sede changed, LeadService re-derives it.
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'extra_fields' => ['sometimes', 'nullable', 'array'],
            'extra_fields.*' => ['string'],
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
        /** @var Model $lead */
        $lead = $this->route('lead');

        return $lead;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateLeadData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateLeadData::fromValidated($validated);
    }
}
