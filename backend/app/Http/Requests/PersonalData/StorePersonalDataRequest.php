<?php

namespace App\Http\Requests\PersonalData;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Http\Requests\Concerns\FormatsPersonalDataInput;
use App\Http\Requests\Concerns\ResolvesOwner;
use App\Rules\TaxCode;
use App\Rules\VatNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/personal-data.
 *
 * Per-type requirements: an individual must carry first/last name, a company
 * must carry a company name. The card is attached to a polymorphic owner
 * (personable_type/personable_id) resolved through the config allowlist; the
 * one-card-per-owner invariant (morphOne) is enforced here at the boundary.
 * Authorization stays in the controller via the PersonalDataPolicy.
 */
class StorePersonalDataRequest extends FormRequest
{
    use FormatsPersonalDataInput, ResolvesOwner;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the PersonalDataPolicy.
        return true;
    }

    /**
     * Canonicalize the typed identity values before the rules run (user
     * directive 2026-07-23) — see FormatsPersonalDataInput. Inherited by
     * UpdatePersonalDataRequest, so both write paths store the same shape.
     */
    protected function prepareForValidation(): void
    {
        $this->formatIdentityInput();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->domainRules(), $this->ownerRules());
    }

    /**
     * The entity's own validation rules, owner-agnostic. Kept separate so the
     * UpdatePersonalDataRequest reuses them verbatim and the existing unit tests
     * keep validating the domain rules in isolation.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function domainRules(): array
    {
        $isCompany = $this->input('type') === PersonalDataTypeEnum::Company->value;
        $isIndividual = $this->input('type') === PersonalDataTypeEnum::Individual->value;

        return [
            'type' => ['required', Rule::enum(PersonalDataTypeEnum::class)],

            'first_name' => [$isIndividual ? 'required' : 'nullable', 'string', 'max:255'],
            'last_name' => [$isIndividual ? 'required' : 'nullable', 'string', 'max:255'],
            'company_name' => [$isCompany ? 'required' : 'nullable', 'string', 'max:255'],

            'tax_code' => ['nullable', 'string', 'max:32', new TaxCode],
            'vat_number' => ['nullable', 'string', 'max:32', new VatNumber],
            'sdi_code' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'birth_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'gender' => ['nullable', Rule::enum(GenderEnum::class)],
        ];
    }

    /**
     * Enforce a valid, existing owner that does not already hold a card.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOwner($validator);
            $this->guardSingleCard($validator);
        });
    }

    /**
     * A model owns exactly one personal-data card (morphOne): reject a second.
     */
    private function guardSingleCard(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return; // owner missing/unknown already reported — nothing to check
        }

        if ($this->owner()->personalData()->exists()) {
            $validator->errors()->add($this->ownerIdField(), 'This owner already has a personal-data card.');
        }
    }

    protected function ownerConfigKey(): string
    {
        return 'personal_data.personable_types';
    }

    protected function ownerTypeField(): string
    {
        return 'personable_type';
    }

    protected function ownerIdField(): string
    {
        return 'personable_id';
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreatePersonalData
    {
        return new CreatePersonalData(
            type: PersonalDataTypeEnum::from($this->string('type')->toString()),
            firstName: $this->input('first_name'),
            lastName: $this->input('last_name'),
            companyName: $this->input('company_name'),
            taxCode: $this->input('tax_code'),
            vatNumber: $this->input('vat_number'),
            sdiCode: $this->input('sdi_code'),
            birthDate: $this->input('birth_date'),
            birthCityId: $this->filled('birth_city_id') ? (int) $this->input('birth_city_id') : null,
            gender: $this->input('gender'),
        );
    }
}
