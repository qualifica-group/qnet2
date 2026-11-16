<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\Sources\Concerns\MapsExternalProfileRecord;

/**
 * The profile of a legacy "Referente 1/2" (spec 0398 `responsible_referents`): the
 * legacy sends the name already split (a single word is the first name, D-6) and the
 * channels already cleaned. The phone is the primary one, the second phone is not.
 */
final class ResponsibleReferentProfile
{
    use MapsExternalProfileRecord;

    public function __construct(private readonly MigrationGeoResolver $geoResolver) {}

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<int, string>  $warnings
     */
    public function profile(array $entry, array &$warnings): ?ProfileData
    {
        $firstName = $this->blankToNull($entry['first_name'] ?? null);

        if ($firstName === null) {
            return null;
        }

        [$contacts, $contactWarnings] = $this->buildContactInputs($entry, [
            ['field' => 'email', 'type' => ContactTypeEnum::Email, 'label' => 'Email'],
            ['field' => 'pec', 'type' => ContactTypeEnum::Pec, 'label' => 'PEC'],
            ['field' => 'phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Telefono'],
            ['field' => 'phone2', 'type' => ContactTypeEnum::Phone, 'label' => null, 'primary' => false],
            ['field' => 'fax', 'type' => ContactTypeEnum::Fax, 'label' => 'Fax'],
        ]);
        [$extraContacts, $extraWarnings] = $this->buildExtraContacts($entry);
        array_push($warnings, ...$contactWarnings, ...$extraWarnings);
        $contacts = [...$contacts, ...$extraContacts];

        return new ProfileData(
            card: new CreatePersonalData(
                type: PersonalDataTypeEnum::Individual,
                firstName: $firstName,
                lastName: $this->blankToNull($entry['last_name'] ?? null),
            ),
            contacts: $contacts === [] ? null : $contacts,
        );
    }
}
