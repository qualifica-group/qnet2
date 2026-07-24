<?php

namespace App\Http\Requests\CompanySites;

use App\DataObjects\CompanySites\CreateCompanySiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/company-sites (spec 0020).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', CompanySite::class)). Reuses ValidatesUserProfile
 * verbatim for the nested `personal_data` card (contacts + address), exactly
 * like StoreRegistryRequest; `name` stays the site's own required column.
 * `banks` are validated per-row (BankService::sync). `is_default` is never
 * accepted here — EnforcesFieldPermissions (spec 0004) additionally rejects
 * any submitted field the actor cannot edit (create-context, model = null).
 */
class StoreCompanySiteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CompanySitePolicy.
        return true;
    }

    /**
     * `personal_data` is OPTIONAL on create (the frontend always sends it, but
     * `name` is the site's own required column, not derived from the card —
     * unlike Registry, where the profile is mandatory as the name source).
     */
    protected function profileRequired(): bool
    {
        return false;
    }

    /**
     * A present address must be geo-located on create (product decision): both
     * `line1` and `city_id` are required. Update keeps `city_id` optional
     * (ValidatesUserProfile default) so a legacy address without a city stays
     * editable.
     */
    protected function addressCityRequired(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,gif,webp', 'extensions:jpeg,png,gif,webp', 'max:'.(int) config('attachments.max_size')],

            'banks' => ['sometimes', 'array'],
            'banks.*.id' => ['sometimes', 'integer', 'min:1'],
            'banks.*.name' => ['required', 'string', 'max:191'],
            // No SEPA/ISO-13616 shape constraint (product decision): a bank's
            // IBAN is free text, only capped in length.
            'banks.*.iban' => ['nullable', 'string', 'max:50'],
            'banks.*.notes' => ['nullable', 'string', 'max:191'],
            'banks.*.is_primary' => ['sometimes', 'boolean'],

            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')],
        ], $this->cappedProfileRules());
    }

    /**
     * The shared nested-profile rules (ValidatesUserProfile), with the
     * company-site cap of AT MOST ONE address applied on top: a site owns a
     * single address, so `personal_data.addresses` is validated `max:1`. This
     * clean array override (instead of an after-hook count check) keeps the
     * cap a first-class validation rule reported at the field path.
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateCompanySiteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        /** @var UploadedFile|null $logo */
        $logo = $this->file('logo');

        return CreateCompanySiteData::fromValidated($validated, $logo);
    }
}
