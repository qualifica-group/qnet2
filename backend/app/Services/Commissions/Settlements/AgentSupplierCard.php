<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Referent;

/**
 * Turns the card of an agent (referent) into the payload of a supplier
 * registry (spec 0267, D-7): the type, names, codes, contacts and addresses are
 * copied as they are, `is_supplier` is true. The referent must have the card
 * with its contacts and addresses loaded.
 */
final class AgentSupplierCard
{
    /**
     * The card fields a supplier registry cannot be created without.
     *
     * @return array<int, string>
     */
    public function missingFields(Referent $referent): array
    {
        $card = $referent->personalData;

        if ($card === null) {
            return ['personal_data'];
        }

        $missing = $card->type === PersonalDataTypeEnum::Company
            ? $this->blank($card->company_name, 'company_name')
            : [...$this->blank($card->first_name, 'first_name'), ...$this->blank($card->last_name, 'last_name')];

        if ($this->isBlank($card->tax_code) && $this->isBlank($card->vat_number)) {
            $missing[] = 'tax_code';
        }

        foreach ($card->addresses->values() as $index => $address) {
            if ($this->isBlank($address->line1) || $address->city_id === null) {
                $missing[] = "addresses.{$index}";
            }
        }

        return $missing;
    }

    public function registryData(): CreateRegistryData
    {
        return new CreateRegistryData(
            sourceId: null,
            sectorIds: null,
            referentIds: null,
            managerSlots: null,
            supervisorId: null,
            commercialId: null,
            reporterId: null,
            vatGroup: null,
            isSupplier: true,
            isQualifiedSupplier: false,
            agreementStatus: null,
            agreementNotes: null,
            sizeClass: null,
            employeeCount: null,
        );
    }

    public function profile(Referent $referent): ProfileData
    {
        $card = $referent->personalData;

        return new ProfileData(
            card: new CreatePersonalData(
                type: $card->type,
                firstName: $card->first_name,
                lastName: $card->last_name,
                companyName: $card->company_name,
                taxCode: $card->tax_code,
                vatNumber: $card->vat_number,
                sdiCode: $card->sdi_code,
                birthDate: $card->birth_date?->toDateString(),
                birthCityId: $card->birth_city_id,
                residenceCityId: $card->residence_city_id,
                gender: $card->gender?->value,
            ),
            contacts: $card->contacts->map(fn (Contact $contact): ContactInput => new ContactInput(null, new CreateContact(
                $contact->type, (string) $contact->value, $contact->label, (bool) $contact->is_primary,
            )))->values()->all(),
            addresses: $card->addresses->map(fn (Address $address): AddressInput => new AddressInput(null, new CreateAddress(
                line1: (string) $address->line1,
                line2: $address->line2,
                postalCode: $address->postal_code,
                cityId: $address->city_id,
                provinceId: $address->province_id,
                stateId: $address->state_id,
                countryId: $address->country_id,
                latitude: $address->latitude,
                longitude: $address->longitude,
                isPrimary: (bool) $address->is_primary,
                siteType: $address->site_type,
            )))->values()->all(),
        );
    }

    /**
     * @return array<int, string>
     */
    private function blank(?string $value, string $field): array
    {
        return $this->isBlank($value) ? [$field] : [];
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || trim($value) === '';
    }
}
