<?php

namespace App\Http\Requests\Leads;

use App\DataObjects\Leads\UpdateLeadData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Lead;
use App\Services\Leads\LeadSourceResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/leads/{lead} (spec 0024, spec 0041
 * D-1). Every field is `sometimes` (partial PATCH); `registry_id`/
 * `campaign_id`, IF submitted, cannot be null (BR-1 — mandatory fields never
 * accept an empty value once touched). `source_id`, IF submitted, is required
 * unless the lead's campaign (the submitted one, else the current one) names
 * a Fonte to inherit (spec 0176, D-2/D-3). Lead status is derived and is not
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
            'source_id' => ['sometimes', Rule::requiredIf(fn (): bool => ! $this->campaignNamesSource()), 'nullable', 'integer', Rule::exists('sources', 'id')],
            'operator_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
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

    private function campaignNamesSource(): bool
    {
        /** @var Lead $lead */
        $lead = $this->route('lead');
        $campaignId = $this->has('campaign_id') ? $this->input('campaign_id') : $lead->campaign_id;

        if (! is_numeric($campaignId)) {
            return false;
        }

        return app(LeadSourceResolver::class)->resolve(null, (int) $campaignId) !== null;
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
