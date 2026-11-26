<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Registry;
use App\Models\User;
use App\Services\Commissions\Settlements\AgentSupplierCard;
use App\Services\RegistryService;

/**
 * Creates the supplier registry of an employee from the user's personal data
 * card (spec 0431, D-4), through `RegistryService::create()` like an agent
 * supplier: person type, names and tax code; contacts and addresses are copied
 * only when complete.
 */
final class PayslipSupplierCreator
{
    public function __construct(
        private readonly RegistryService $registries,
        private readonly AgentSupplierCard $card,
    ) {}

    public function create(int $userId, User $actor): Registry
    {
        $user = User::query()->with(['personalData.contacts', 'personalData.addresses'])->findOrFail($userId);

        return $this->registries->create($actor, $this->card->registryData(), $this->profile($user));
    }

    private function profile(User $user): ProfileData
    {
        $card = $user->personalData;

        return new ProfileData(
            card: new CreatePersonalData(
                type: PersonalDataTypeEnum::Individual,
                firstName: $card?->first_name,
                lastName: filled($card?->last_name) ? $card->last_name : $user->name,
                taxCode: $card?->tax_code,
                birthDate: $card?->birth_date?->toDateString(),
                birthCityId: $card?->birth_city_id,
                residenceCityId: $card?->residence_city_id,
                gender: $card?->gender?->value,
            ),
            contacts: ($card?->contacts ?? collect())
                ->filter(static fn (Contact $contact): bool => filled($contact->value))
                ->map(static fn (Contact $contact): ContactInput => new ContactInput(null, new CreateContact(
                    $contact->type, (string) $contact->value, $contact->label, (bool) $contact->is_primary,
                )))->values()->all(),
            addresses: ($card?->addresses ?? collect())
                ->filter(static fn (Address $address): bool => filled($address->line1) && $address->city_id !== null)
                ->map(static fn (Address $address): AddressInput => new AddressInput(null, new CreateAddress(
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
}
