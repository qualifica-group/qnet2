<?php

namespace App\Http\Requests\CompanySites;

use App\DataObjects\CompanySites\UpdateCompanySiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\CompanySite;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/company-sites/{companySite}
 * (spec 0020). Every field is `sometimes` to support partial PATCH updates.
 * `personal_data` present rewrites the site's card (contacts + its single
 * address); a present `banks` key is AUTHORITATIVE (add/update/delete diff,
 * BankService::sync).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $companySite)). EnforcesFieldPermissions (spec 0004)
 * rejects any submitted field the actor cannot edit.
 */
class UpdateCompanySiteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CompanySitePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'notes' => ['sometimes', 'nullable', 'string'],

            'banks' => ['sometimes', 'array'],
            'banks.*.id' => ['sometimes', 'integer', 'min:1'],
            'banks.*.name' => ['required', 'string', 'max:191'],
            // No SEPA/ISO-13616 shape constraint (product decision): a bank's
            // IBAN is free text, only capped in length.
            'banks.*.iban' => ['nullable', 'string', 'max:50'],
            'banks.*.notes' => ['nullable', 'string', 'max:191'],
            'banks.*.is_primary' => ['sometimes', 'boolean'],

            'company_id' => ['sometimes', 'nullable', 'integer', Rule::exists('companies', 'id')],
        ], $this->cappedProfileRules());
    }

    /**
     * The shared nested-profile rules (ValidatesUserProfile), with the
     * company-site cap of AT MOST ONE address applied on top: a site owns a
     * single address, so `personal_data.addresses` is validated `max:1`. Clean
     * array override (instead of an after-hook count check) so the cap is a
     * first-class validation rule reported at the field path.
     *
     * @return array<string, array<int, mixed>>
     */
    private function cappedProfileRules(): array
    {
        $rules = $this->profileRules();
        $rules['personal_data.addresses'] = ['sometimes', 'array', 'max:1'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'company-sites';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var CompanySite $companySite */
        $companySite = $this->route('companySite');

        return $companySite;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCompanySiteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCompanySiteData::fromValidated($validated);
    }
}
