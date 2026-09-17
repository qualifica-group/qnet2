<?php

namespace App\Imports\Leads;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Contact;
use App\Models\Registry;
use Illuminate\Support\Collection;

/**
 * Builds the `ProfileData` (card + contacts + address) RegistryService needs
 * from a staged leads-import row's merged mapped+recognized values (spec
 * 0033: `full_name`/`first_name`/`last_name`/`company_name`/`tax_code`/
 * `vat_number` for the card, `email`/`phone` for contacts,
 * `street`/`postal_code` + the GeoRecognizer's `*_id` for the address).
 * spec 0041 D-1: the import row's contact is an Anagrafica (Registry), not a
 * Referent.
 *
 * The `update_existing` strategy reuses the SAME shape but MERGES onto the
 * matched Registry's current contacts/addresses instead of replacing them
 * wholesale: ContactService::sync()/AddressService::sync() are authoritative
 * (an omitted row is deleted), so a row that only carries an email must never
 * wipe out the Registry's existing phone/pec/website channels or its address
 * — every currently-owned row not touched by this import is re-submitted
 * with its own id + unchanged data, only the matching type(s)/the address are
 * overwritten with the row's new value.
 */
final class LeadProfileBuilder
{
    /**
     * `create_new` shape: a fresh card, no existing rows to preserve.
     *
     * @param  array<string, mixed>  $mapped
     */
    public function build(array $mapped): ProfileData
    {
        return new ProfileData(
            card: $this->buildCard($mapped),
            contacts: $this->buildContacts($mapped),
            addresses: $this->buildAddresses($mapped),
        );
    }

    /**
     * `update_existing` shape: the card is replaced (an import row is always
     * authoritative on the anagraphic fields it carries), but contacts/
     * addresses the row does NOT mention are preserved untouched.
     *
     * @param  array<string, mixed>  $mapped
     */
    public function buildForUpdate(Registry $registry, array $mapped): ProfileData
    {
        return new ProfileData(
            card: $this->buildCard($mapped),
            contacts: $this->mergeContacts($registry, $mapped),
            addresses: $this->mergeAddresses($registry, $mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildCard(array $mapped): CreatePersonalData
    {
        $companyName = $this->value($mapped, 'company_name');

        return new CreatePersonalData(
            type: $companyName !== null ? PersonalDataTypeEnum::Company : PersonalDataTypeEnum::Individual,
            firstName: $this->value($mapped, 'first_name'),
            lastName: $this->value($mapped, 'last_name'),
            companyName: $companyName,
            taxCode: $this->value($mapped, 'tax_code'),
            vatNumber: $this->value($mapped, 'vat_number'),
        );
    }

    /**
     * Every contact of the row is the PRIMARY of its own type (spec 0139 D-3):
     * the row holds at most one value per type, and the grids read primaries
     * only — flagging just the first one left the phone invisible.
     *
     * @param  array<string, mixed>  $mapped
     * @return array<int, ContactInput>|null
     */
    private function buildContacts(array $mapped): ?array
    {
        $values = $this->contactValues($mapped);

        if ($values === []) {
            return null;
        }

        $inputs = [];

        foreach ($values as $typeValue => $value) {
            $inputs[] = new ContactInput(null, new CreateContact(ContactTypeEnum::from($typeValue), $value, null, true));
        }

        return $inputs;
    }

    /**
     * The row's value overwrites, for each type it carries, the Registry's
     * primary contact of that type (else its first one) and makes it primary;
     * a type the Registry lacks is added as primary (spec 0139 D-3).
     *
     * @param  array<string, mixed>  $mapped
     * @return array<int, ContactInput>|null null leaves the Registry's
     *                                       contacts entirely untouched (the row carries neither email nor phone)
     */
    private function mergeContacts(Registry $registry, array $mapped): ?array
    {
        $newValues = $this->contactValues($mapped);

        if ($newValues === []) {
            return null;
        }

        /** @var Collection<int, Contact> $owned */
        $owned = $registry->personalData?->contacts ?? collect();
        $targetIds = $this->mergeTargetIds($owned, array_keys($newValues));
        $inputs = [];

        foreach ($owned as $contact) {
            $typeValue = $contact->type->value;
            $isTarget = ($targetIds[$typeValue] ?? null) === $contact->id;

            // Every non-target contact is re-submitted unchanged, so the
            // authoritative sync() never deletes it.
            $inputs[] = new ContactInput($contact->id, new CreateContact(
                $contact->type,
                $isTarget ? $newValues[$typeValue] : $contact->value,
                $contact->label,
                $isTarget || $contact->is_primary,
            ));
        }

        foreach ($newValues as $typeValue => $value) {
            if (! array_key_exists($typeValue, $targetIds)) {
                $inputs[] = new ContactInput(null, new CreateContact(ContactTypeEnum::from($typeValue), $value, null, true));
            }
        }

        return $inputs;
    }

    /**
     * For each contact type the row carries, the id of the owned contact it
     * overwrites: the primary of that type, else the first of that type.
     *
     * @param  Collection<int, Contact>  $owned
     * @param  array<int, string>  $typeValues
     * @return array<string, int> contact type value => contact id
     */
    private function mergeTargetIds(Collection $owned, array $typeValues): array
    {
        $targetIds = [];

        foreach ($typeValues as $typeValue) {
            $ofType = $owned->filter(static fn (Contact $contact): bool => $contact->type->value === $typeValue);
            $target = $ofType->first(static fn (Contact $contact): bool => $contact->is_primary) ?? $ofType->first();

            if ($target instanceof Contact) {
                $targetIds[$typeValue] = $target->id;
            }
        }

        return $targetIds;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<string, string> contact type value => raw value
     */
    private function contactValues(array $mapped): array
    {
        $values = [];

        foreach (LeadContactFields::map() as $field => $type) {
            $value = $this->value($mapped, $field);

            if ($value !== null) {
                $values[$type->value] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, AddressInput>|null
     */
    private function buildAddresses(array $mapped): ?array
    {
        $address = $this->buildAddressData($mapped);

        return $address === null ? null : [new AddressInput(null, $address)];
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @return array<int, AddressInput>|null null leaves the Registry's
     *                                       addresses untouched (the row carries no `street`)
     */
    private function mergeAddresses(Registry $registry, array $mapped): ?array
    {
        $address = $this->buildAddressData($mapped);

        if ($address === null) {
            return null;
        }

        $existing = $registry->personalData?->addresses?->firstWhere('is_primary', true)
            ?? $registry->personalData?->addresses?->first();

        return [new AddressInput($existing?->id, $address)];
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function buildAddressData(array $mapped): ?CreateAddress
    {
        $street = $this->value($mapped, 'street');

        if ($street === null) {
            return null;
        }

        return new CreateAddress(
            line1: $street,
            postalCode: $this->value($mapped, 'postal_code'),
            cityId: $this->id($mapped, 'city_id'),
            provinceId: $this->id($mapped, 'province_id'),
            stateId: $this->id($mapped, 'state_id'),
            countryId: $this->id($mapped, 'country_id'),
            isPrimary: true,
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function value(array $mapped, string $field): ?string
    {
        $value = trim((string) ($mapped[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function id(array $mapped, string $field): ?int
    {
        $value = $mapped[$field] ?? null;

        return $value === null ? null : (int) $value;
    }
}
