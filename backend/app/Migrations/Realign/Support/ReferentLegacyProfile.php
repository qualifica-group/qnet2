<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\GenderEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\Sources\Concerns\MapsExternalProfileRecord;
use App\Migrations\Support\MigrationGeoResolver;
use RuntimeException;

/**
 * The profile of a legacy `contacts` record, with the channels of the referents
 * import: the `mobile` is the second phone and, being the last, the primary one.
 */
final class ReferentLegacyProfile
{
    use MapsExternalProfileRecord;

    public function __construct(private readonly MigrationGeoResolver $geoResolver) {}

    /**
     * @param  array<string, mixed>  $legacy
     * @param  array<int, string>  $warnings
     *
     * @throws RuntimeException when the name is incomplete, as at import
     */
    public function profile(array $legacy, array &$warnings): ProfileData
    {
        $firstName = $this->blankToNull($legacy['first_name'] ?? null);
        $lastName = $this->blankToNull($legacy['last_name'] ?? null);

        if ($firstName === null || $lastName === null) {
            throw new RuntimeException('first_name and last_name are required.');
        }

        [$address, $addressWarnings] = $this->buildAddress($legacy);
        [$contacts, $contactWarnings] = $this->buildContactInputs($legacy, [
            ['field' => 'email', 'type' => ContactTypeEnum::Email, 'label' => 'Email'],
            ['field' => 'pec', 'type' => ContactTypeEnum::Pec, 'label' => 'PEC'],
            ['field' => 'phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Telefono'],
            ['field' => 'mobile', 'type' => ContactTypeEnum::Phone, 'label' => null],
            ['field' => 'fax', 'type' => ContactTypeEnum::Fax, 'label' => 'Fax'],
        ]);
        array_push($warnings, ...$addressWarnings, ...$contactWarnings);

        return new ProfileData(
            card: new CreatePersonalData(
                type: PersonalDataTypeEnum::Individual,
                firstName: $firstName,
                lastName: $lastName,
                taxCode: $this->blankToNull($legacy['tax_code'] ?? null),
                vatNumber: $this->blankToNull($legacy['vat_number'] ?? null),
                birthDate: $this->blankToNull($legacy['birth_date'] ?? null),
                gender: GenderEnum::tryFrom((string) ($legacy['gender'] ?? ''))?->value,
            ),
            contacts: $contacts === [] ? null : $contacts,
            addresses: $address === null ? null : [$address],
        );
    }
}
