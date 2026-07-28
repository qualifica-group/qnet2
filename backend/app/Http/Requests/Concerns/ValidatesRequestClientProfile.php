<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Rules\TaxCode;
use App\Rules\VatNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation + DTO assembly for the client anagraphic block the work panel
 * writes back (spec 0049 amendment): `client_identity`, `client_contacts` and
 * `client_address`, all landing on the Registry's PersonalData card.
 *
 * Deliberately NOT `ValidatesUserProfile`: that trait validates the SAME card
 * but nested under a `personal_data` key and paired with the users' account
 * fields. The identity rules below are its exact per-type mirror (same
 * PersonalDataTypeEnum/GenderEnum sources, same lengths) so the two surfaces
 * cannot drift, while the child collections stay this panel's narrower subset
 * (single address, no site type/coordinates). Writing the identity here also
 * moves `registries.name`, which is derived from the card — the writer keeps
 * that derivation in the same write.
 *
 * Sparse semantics, consistent with the rest of the PATCH payload: an absent
 * key leaves that side untouched. `client_contacts` present (even empty) is
 * AUTHORITATIVE — it is synced against the card's full contact set.
 * `client_address` is a single create-or-update row, never an authoritative
 * sync: the client's other addresses are out of this panel's reach.
 * `client_identity` is a full replace of the card's identity fields.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesRequestClientProfile
{
    use FormatsPersonalDataInput;

    /**
     * Canonicalize the typed identity/contact values before the rules run
     * (user directive 2026-07-23) — see FormatsPersonalDataInput. A host class
     * defining its own prepareForValidation() would take over, so it must call
     * this one.
     */
    protected function prepareForValidation(): void
    {
        $this->formatIdentityInput('client_identity');
        $this->formatContactRowsInput('client_contacts');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function clientProfileRules(): array
    {
        $hasAddress = $this->has('client_address') && $this->input('client_address') !== null;
        $hasIdentity = $this->has('client_identity') && $this->input('client_identity') !== null;

        return [
            ...$this->clientIdentityRules($hasIdentity),

            'client_contacts' => ['sometimes', 'array'],
            'client_contacts.*.id' => ['sometimes', 'integer', 'min:1'],
            'client_contacts.*.type' => ['required', Rule::enum(ContactTypeEnum::class)],
            // The per-type `value` rules run in validateClientProfile() (they
            // depend on each row's own type); here only the base shape.
            'client_contacts.*.value' => ['required', 'string', 'max:255'],
            'client_contacts.*.label' => ['nullable', 'string', 'max:255'],
            'client_contacts.*.is_primary' => ['sometimes', 'boolean'],

            'client_address' => ['sometimes', 'nullable', 'array'],
            'client_address.id' => ['sometimes', 'integer', 'min:1'],
            'client_address.line1' => [Rule::requiredIf($hasAddress), 'string', 'max:255'],
            'client_address.line2' => ['nullable', 'string', 'max:255'],
            'client_address.postal_code' => ['nullable', 'string', 'max:20'],
            'client_address.city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'client_address.province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'client_address.state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'client_address.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    /**
     * The card's identity fields, mirroring `ValidatesUserProfile::profileRules()`
     * one-to-one: the per-type requirement (individual => first+last name,
     * company => company name) is what keeps the derived `registries.name`
     * from ever resolving to an empty string.
     *
     * @return array<string, array<int, mixed>>
     */
    private function clientIdentityRules(bool $hasIdentity): array
    {
        return [
            'client_identity' => ['sometimes', 'nullable', 'array'],
            'client_identity.type' => [
                Rule::requiredIf($hasIdentity),
                Rule::enum(PersonalDataTypeEnum::class),
            ],
            'client_identity.first_name' => [
                Rule::requiredIf(fn (): bool => $this->input('client_identity.type') === PersonalDataTypeEnum::Individual->value),
                'nullable',
                'string',
                'max:255',
            ],
            'client_identity.last_name' => [
                Rule::requiredIf(fn (): bool => $this->input('client_identity.type') === PersonalDataTypeEnum::Individual->value),
                'nullable',
                'string',
                'max:255',
            ],
            'client_identity.company_name' => [
                Rule::requiredIf(fn (): bool => $this->input('client_identity.type') === PersonalDataTypeEnum::Company->value),
                'nullable',
                'string',
                'max:255',
            ],
            'client_identity.tax_code' => ['nullable', 'string', 'max:32', new TaxCode('client_identity.')],
            'client_identity.vat_number' => ['nullable', 'string', 'max:32', new VatNumber],
            'client_identity.sdi_code' => ['nullable', 'string', 'max:32'],
            'client_identity.birth_date' => ['nullable', 'date', 'before:today'],
            'client_identity.birth_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'client_identity.residence_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'client_identity.gender' => ['nullable', Rule::enum(GenderEnum::class)],
        ];
    }

    /**
     * Applies the per-type contact `value` rules, reusing
     * ContactTypeEnum::valueRules() exactly like ValidatesUserProfile, with
     * errors mapped to `client_contacts.{i}.value`.
     */
    protected function validateClientProfile(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        /** @var array<int, mixed> $rows */
        $rows = (array) $this->input('client_contacts', []);

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $valueRules = ContactTypeEnum::tryFrom((string) ($row['type'] ?? ''))?->valueRules() ?? [];

            if ($valueRules === []) {
                continue;
            }

            $rowValidator = validator(['value' => $row['value'] ?? null], ['value' => $valueRules]);

            foreach ($rowValidator->errors()->get('value') as $message) {
                $validator->errors()->add("client_contacts.{$index}.value", $message);
            }
        }
    }

    /**
     * The typed, sparse client-profile slice of the PATCH payload: a key is
     * present only when it was submitted, so the service keeps its
     * array_key_exists() semantics for these two as for every other key.
     *
     * @return array{client_identity?: CreatePersonalData, client_contacts?: array<int, ContactInput>, client_address?: AddressInput}
     */
    public function clientProfilePayload(): array
    {
        $payload = [];

        if ($this->has('client_identity') && $this->input('client_identity') !== null) {
            $payload['client_identity'] = $this->buildClientIdentity();
        }

        if ($this->has('client_contacts')) {
            $payload['client_contacts'] = $this->buildClientContacts();
        }

        if ($this->has('client_address') && $this->input('client_address') !== null) {
            $payload['client_address'] = $this->buildClientAddress();
        }

        return $payload;
    }

    private function buildClientIdentity(): CreatePersonalData
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->input('client_identity', []);

        return new CreatePersonalData(
            type: PersonalDataTypeEnum::from((string) ($row['type'] ?? '')),
            firstName: $row['first_name'] ?? null,
            lastName: $row['last_name'] ?? null,
            companyName: $row['company_name'] ?? null,
            taxCode: $row['tax_code'] ?? null,
            vatNumber: $row['vat_number'] ?? null,
            sdiCode: $row['sdi_code'] ?? null,
            birthDate: $row['birth_date'] ?? null,
            birthCityId: isset($row['birth_city_id']) ? (int) $row['birth_city_id'] : null,
            residenceCityId: isset($row['residence_city_id']) ? (int) $row['residence_city_id'] : null,
            gender: $row['gender'] ?? null,
        );
    }

    /**
     * @return array<int, ContactInput>
     */
    private function buildClientContacts(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = (array) $this->input('client_contacts', []);

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

    private function buildClientAddress(): AddressInput
    {
        /** @var array<string, mixed> $row */
        $row = (array) $this->input('client_address', []);

        return new AddressInput(
            id: isset($row['id']) ? (int) $row['id'] : null,
            data: new CreateAddress(
                line1: (string) ($row['line1'] ?? ''),
                line2: $row['line2'] ?? null,
                postalCode: $row['postal_code'] ?? null,
                cityId: isset($row['city_id']) ? (int) $row['city_id'] : null,
                provinceId: isset($row['province_id']) ? (int) $row['province_id'] : null,
                stateId: isset($row['state_id']) ? (int) $row['state_id'] : null,
                countryId: isset($row['country_id']) ? (int) $row['country_id'] : null,
            ),
        );
    }
}
