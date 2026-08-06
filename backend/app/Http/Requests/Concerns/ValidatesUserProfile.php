<?php

namespace App\Http\Requests\Concerns;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\SiteTypeEnum;
use App\Rules\TaxCode;
use App\Rules\UniquePersonalDataIdentifier;
use App\Rules\VatNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared validation + DTO assembly for the optional nested `personal_data` object
 * accepted by the user write endpoints (ADR 0012, spec 0003). Used verbatim by
 * both StoreUserRequest and UpdateUserRequest so the nested rules live in one
 * place and stay consistent across create and edit.
 *
 * The nested rules deliberately reuse the SAME domain rule sources as the
 * per-entity endpoints (the enums and the per-type contact `value` rules), so
 * the two validation surfaces never drift. `personal_data` absent leaves the card
 * untouched; a present `contacts`/`addresses` key is authoritative.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesUserProfile
{
    use FormatsPersonalDataInput;

    /**
     * Canonicalize the typed identity/contact values before the rules run
     * (user directive 2026-07-23) — see FormatsPersonalDataInput.
     *
     * Lives on the trait so all eight requests that compose it inherit it; a
     * host class defining its own prepareForValidation() would silently take
     * over, so it must call this one.
     */
    protected function prepareForValidation(): void
    {
        $this->formatIdentityInput('personal_data');
        $this->formatContactRowsInput('personal_data.contacts');
    }

    /**
     * Whether the nested `personal_data` object is mandatory on this request.
     *
     * On create it is the ONLY source of the derived `users.name`, so it is
     * required; on update it stays optional (absent → name untouched). Requests
     * override this; the default is the optional (update) behavior.
     */
    protected function profileRequired(): bool
    {
        return false;
    }

    /**
     * Whether a present address row must also carry `city_id`, on top of the
     * always-required `line1`.
     *
     * Product decision: on CREATE, an inline address is only useful once it is
     * geo-located, so `city_id` becomes mandatory there. UPDATE stays optional
     * (default false) so editing a legacy address whose city was never
     * captured keeps working — Store* requests override this to true, Update*
     * requests never override it.
     */
    protected function addressCityRequired(): bool
    {
        return false;
    }

    /**
     * The entity owning the card this surface writes, or null when the surface
     * enforces no identity uniqueness at all.
     *
     * Non-null turns the constraint on: the submitted fiscal identifiers (and,
     * where the host also composes ValidatesPhoneUniqueness, the phone rows)
     * must be free across the whole `IdentityUniquenessScope` namespace — users,
     * anagrafiche and referenti (user directive 2026-08-06) — with this owner's
     * own card excluded from the lookup.
     *
     * Null by default so the USER write endpoints stay unconstrained: the
     * directive blocks the creation of an anagrafica or a referente on a value
     * an account already holds, not the other way round. The four
     * referent/registry requests override it.
     *
     * @return class-string<Model>|null
     */
    protected function identityUniquenessOwner(): ?string
    {
        return null;
    }

    /**
     * The owner being updated, whose own card must not be counted as a
     * collision with itself. Null on create (nothing to exclude yet).
     */
    protected function identityUniquenessOwnerId(): ?int
    {
        return null;
    }

    /**
     * Validation rules for the nested `personal_data.*` object. Merged into each
     * request's own account-field rules; they never change the account rules.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function profileRules(): array
    {
        $required = $this->profileRequired();

        // When the profile is required (create) it is always present, so the
        // per-type identity rules always apply; otherwise (update) they apply only
        // when `personal_data` was actually submitted.
        $hasProfile = $required || $this->has('personal_data');

        return [
            'personal_data' => $required
                ? ['required', 'array']
                : ['sometimes', 'nullable', 'array'],

            'personal_data.type' => [
                Rule::requiredIf($hasProfile),
                Rule::enum(PersonalDataTypeEnum::class),
            ],
            'personal_data.first_name' => [
                Rule::requiredIf(fn (): bool => $this->input('personal_data.type') === PersonalDataTypeEnum::Individual->value),
                'nullable',
                'string',
                'max:255',
            ],
            'personal_data.last_name' => [
                Rule::requiredIf(fn (): bool => $this->input('personal_data.type') === PersonalDataTypeEnum::Individual->value),
                'nullable',
                'string',
                'max:255',
            ],
            'personal_data.company_name' => [
                Rule::requiredIf(fn (): bool => $this->input('personal_data.type') === PersonalDataTypeEnum::Company->value),
                'nullable',
                'string',
                'max:255',
            ],

            'personal_data.tax_code' => ['nullable', 'string', 'max:32', new TaxCode('personal_data.'), ...$this->identityUniquenessRules('tax_code')],
            'personal_data.vat_number' => ['nullable', 'string', 'max:32', new VatNumber, ...$this->identityUniquenessRules('vat_number')],
            'personal_data.sdi_code' => ['nullable', 'string', 'max:32'],
            'personal_data.birth_date' => ['nullable', 'date', 'before:today'],
            'personal_data.birth_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'personal_data.residence_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'personal_data.gender' => ['nullable', Rule::enum(GenderEnum::class)],

            // Contacts: present key (even empty) is authoritative.
            'personal_data.contacts' => ['sometimes', 'array'],
            'personal_data.contacts.*.id' => ['sometimes', 'integer', 'min:1'],
            'personal_data.contacts.*.type' => ['required', Rule::enum(ContactTypeEnum::class)],
            // The per-type `value` rules are applied in the validateProfile() hook
            // (they depend on each row's own type), so here we only assert the base.
            'personal_data.contacts.*.value' => ['required', 'string', 'max:255'],
            'personal_data.contacts.*.label' => ['nullable', 'string', 'max:255'],
            'personal_data.contacts.*.is_primary' => ['sometimes', 'boolean'],

            // Addresses: present key (even empty) is authoritative.
            'personal_data.addresses' => ['sometimes', 'array'],
            'personal_data.addresses.*.id' => ['sometimes', 'integer', 'min:1'],
            'personal_data.addresses.*.line1' => ['required', 'string', 'max:255'],
            'personal_data.addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'personal_data.addresses.*.postal_code' => ['nullable', 'string', 'max:20'],
            'personal_data.addresses.*.site_type' => ['nullable', Rule::enum(SiteTypeEnum::class)],
            'personal_data.addresses.*.city_id' => [
                $this->addressCityRequired() ? 'required' : 'nullable',
                'integer',
                Rule::exists('cities', 'id'),
            ],
            'personal_data.addresses.*.province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'personal_data.addresses.*.state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'personal_data.addresses.*.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'personal_data.addresses.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'personal_data.addresses.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'personal_data.addresses.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The uniqueness rule for one identifier column, or nothing when the
     * surface declares no owner (see identityUniquenessOwner).
     *
     * @return array<int, UniquePersonalDataIdentifier>
     */
    private function identityUniquenessRules(string $column): array
    {
        $owner = $this->identityUniquenessOwner();

        return $owner === null
            ? []
            : [new UniquePersonalDataIdentifier($column, $owner, $this->identityUniquenessOwnerId())];
    }

    /**
     * After-hook applying the per-type contact `value` rules, reusing
     * ContactTypeEnum::valueRules() exactly like StoreContactRequest. Each row's
     * value is re-validated against the rules for its own type, with errors mapped
     * to `personal_data.contacts.{i}.value`.
     */
    protected function validateProfile(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            // Base/structure errors already reported; the per-type pass would only
            // add noise on top of a malformed payload.
            return;
        }

        /** @var array<int, array<string, mixed>> $contacts */
        $contacts = (array) $this->input('personal_data.contacts', []);

        foreach ($contacts as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = ContactTypeEnum::tryFrom((string) ($row['type'] ?? ''));
            $valueRules = $type?->valueRules() ?? [];

            if ($valueRules === []) {
                continue;
            }

            $rowValidator = validator(
                ['value' => $row['value'] ?? null],
                ['value' => $valueRules],
            );

            foreach ($rowValidator->errors()->get('value') as $message) {
                $validator->errors()->add("personal_data.contacts.{$index}.value", $message);
            }
        }
    }

    /**
     * Build the typed ProfileData DTO from the submitted nested payload, or null
     * when `personal_data` is absent/null (leave the card untouched).
     *
     * A `contacts`/`addresses` array is built only when its key is present, so a
     * null collection on the DTO means "untouched" while an empty array means
     * "delete all owned children" (authoritative sync). Casting mirrors the
     * per-entity FormRequests' toData() (enums via ::from / ::fromValue, ints via
     * filled() casts).
     */
    public function toProfile(): ?ProfileData
    {
        if (! $this->has('personal_data') || $this->input('personal_data') === null) {
            return null;
        }

        return new ProfileData(
            card: $this->buildCard(),
            contacts: $this->has('personal_data.contacts') ? $this->buildContacts() : null,
            addresses: $this->has('personal_data.addresses') ? $this->buildAddresses() : null,
        );
    }

    private function buildCard(): CreatePersonalData
    {
        return new CreatePersonalData(
            type: PersonalDataTypeEnum::from((string) $this->input('personal_data.type')),
            firstName: $this->input('personal_data.first_name'),
            lastName: $this->input('personal_data.last_name'),
            companyName: $this->input('personal_data.company_name'),
            taxCode: $this->input('personal_data.tax_code'),
            vatNumber: $this->input('personal_data.vat_number'),
            sdiCode: $this->input('personal_data.sdi_code'),
            birthDate: $this->input('personal_data.birth_date'),
            birthCityId: $this->filled('personal_data.birth_city_id')
                ? (int) $this->input('personal_data.birth_city_id')
                : null,
            residenceCityId: $this->filled('personal_data.residence_city_id')
                ? (int) $this->input('personal_data.residence_city_id')
                : null,
            gender: $this->input('personal_data.gender'),
        );
    }

    /**
     * @return array<int, ContactInput>
     */
    private function buildContacts(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) $this->input('personal_data.contacts', []);

        return array_values(array_map(
            fn (array $row): ContactInput => new ContactInput(
                id: isset($row['id']) ? (int) $row['id'] : null,
                data: new CreateContact(
                    type: ContactTypeEnum::from((string) ($row['type'] ?? '')),
                    value: (string) ($row['value'] ?? ''),
                    label: $row['label'] ?? null,
                    isPrimary: (bool) ($row['is_primary'] ?? false),
                ),
            ),
            $rows,
        ));
    }

    /**
     * @return array<int, AddressInput>
     */
    private function buildAddresses(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) $this->input('personal_data.addresses', []);

        return array_values(array_map(
            fn (array $row): AddressInput => new AddressInput(
                id: isset($row['id']) ? (int) $row['id'] : null,
                data: new CreateAddress(
                    line1: (string) ($row['line1'] ?? ''),
                    line2: $row['line2'] ?? null,
                    postalCode: $row['postal_code'] ?? null,
                    siteType: isset($row['site_type']) ? SiteTypeEnum::from((string) $row['site_type']) : null,
                    cityId: isset($row['city_id']) ? (int) $row['city_id'] : null,
                    provinceId: isset($row['province_id']) ? (int) $row['province_id'] : null,
                    stateId: isset($row['state_id']) ? (int) $row['state_id'] : null,
                    countryId: isset($row['country_id']) ? (int) $row['country_id'] : null,
                    latitude: isset($row['latitude']) ? (string) $row['latitude'] : null,
                    longitude: isset($row['longitude']) ? (string) $row['longitude'] : null,
                    isPrimary: (bool) ($row['is_primary'] ?? false),
                ),
            ),
            $rows,
        ));
    }
}
