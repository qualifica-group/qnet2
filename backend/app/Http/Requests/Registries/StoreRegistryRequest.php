<?php

namespace App\Http\Requests\Registries;

use App\DataObjects\Registries\CreateRegistryData;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Http\Requests\Concerns\ValidatesPhoneUniqueness;
use App\Http\Requests\Concerns\ValidatesRequiredPhoneContact;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\Registry;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/registries (spec 0020).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Registry::class)). Reuses
 * ValidatesUserProfile verbatim for the nested `personal_data` object
 * (required on create — it is the only source of the derived
 * `registries.name`, mirroring StoreReferentRequest). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 *
 * On top of those, the same two phone rules the referenti carry: the nested
 * card must hold at least one number at creation (ValidatesRequiredPhoneContact,
 * user directive 2026-09-07) and that number must be free across the shared
 * identity namespace (ValidatesPhoneUniqueness).
 */
class StoreRegistryRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;
    use ValidatesPhoneUniqueness;
    use ValidatesRequiredPhoneContact;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the RegistryPolicy.
        return true;
    }

    /**
     * `personal_data` is mandatory on create: it is the only source of the
     * derived `registries.name` (mirrors StoreReferentRequest).
     */
    protected function profileRequired(): bool
    {
        return true;
    }

    /**
     * A present address must be geo-located on create (product decision): both
     * `line1` and `city_id` are required. Update keeps `city_id` optional
     * (ValidatesUserProfile default) so legacy addresses without a city stay
     * editable.
     */
    protected function addressCityRequired(): bool
    {
        return true;
    }

    /**
     * Codice fiscale, partita IVA and phone numbers must be free across users,
     * anagrafiche and referenti (user directive 2026-08-06); nothing to exclude
     * on create, the anagrafica does not exist yet.
     *
     * @return class-string<Registry>
     */
    protected function identityUniquenessOwner(): ?string
    {
        return Registry::class;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'id')],
            'sector_ids' => ['sometimes', 'array'],
            'sector_ids.*' => ['integer', Rule::exists('sectors', 'id')],
            'referent_ids' => ['sometimes', 'array'],
            'referent_ids.*' => ['integer', Rule::exists('referents', 'id')],
            // Supervisor is an INTERNAL user (like managers); commercial/reporter
            // stay external referents.
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'vat_group' => ['nullable', 'string', 'max:191'],
            'is_supplier' => ['required', 'boolean'],
            'is_qualified_supplier' => ['sometimes', 'boolean'],
            'agreement_status' => ['nullable', Rule::enum(AgreementStatusEnum::class)],
            'agreement_notes' => ['nullable', 'string', 'max:5000'],
            'size_class' => ['nullable', Rule::enum(SizeClassEnum::class)],
            'employee_count' => ['nullable', 'integer', 'min:0'],
        ], $this->managerSlotsRules(), $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile, the
     * create-time phone requirement and the field-level authorization gate
     * (spec 0004).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->validateRequiredPhoneContact($validator);
            $this->validatePhoneUniqueness($validator);
            $this->validateManagerSlots($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'registries';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects). The
     * nested profile is read separately via toProfile() (ValidatesUserProfile).
     */
    public function toData(): CreateRegistryData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateRegistryData::fromValidated($validated);
    }
}
