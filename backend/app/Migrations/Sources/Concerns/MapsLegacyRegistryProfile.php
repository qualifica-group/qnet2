<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Enums\SiteTypeEnum;
use App\Migrations\Sources\RegistriesSource;
use App\Migrations\Support\MigrationGeoResolver;
use RuntimeException;

/**
 * The personal-data profile of a legacy `companies` record (spec 0189, G-11):
 * person card when `is_private`, company card otherwise, contacts and
 * addresses. The legacy sends the values already clean (names split, VAT and
 * tax code normalized, website with scheme). Split out of RegistriesSource,
 * which keeps the orchestration and the relational remaps.
 *
 * @phpstan-require-extends RegistriesSource
 *
 * @property-read MigrationGeoResolver $geoResolver
 */
trait MapsLegacyRegistryProfile
{
    use MapsExternalProfileRecord;

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     *
     * @throws RuntimeException the card would have no name
     */
    private function buildProfile(array $record, array &$warnings): ProfileData
    {
        $card = $this->buildCard($record);

        if ($card->displayName() === '') {
            throw new RuntimeException('The registry has no name (company_name or first_name/last_name are required).');
        }

        [$contacts, $contactWarnings] = $this->buildContacts($record);
        $addresses = $this->buildAddresses($record, $warnings);
        array_push($warnings, ...$contactWarnings);

        return new ProfileData(
            card: $card,
            contacts: $contacts === [] ? null : $contacts,
            addresses: $addresses === [] ? null : $addresses,
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function buildCard(array $record): CreatePersonalData
    {
        $isPrivate = (bool) ($record['is_private'] ?? false);

        return new CreatePersonalData(
            type: $isPrivate ? PersonalDataTypeEnum::Individual : PersonalDataTypeEnum::Company,
            firstName: $this->blankToNull($record['first_name'] ?? null),
            lastName: $this->blankToNull($record['last_name'] ?? null),
            companyName: $isPrivate ? null : $this->blankToNull($record['company_name'] ?? null),
            taxCode: $this->blankToNull($record['tax_code'] ?? null),
            vatNumber: $this->blankToNull($record['vat_number'] ?? null),
            sdiCode: $this->blankToNull($record['sdi_code'] ?? null),
        );
    }

    /**
     * Email, PEC, phone, second phone (non-primary, so the main phone stays
     * the primary one), fax and website.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        return $this->buildContactInputs($record, [
            ['field' => 'email', 'type' => ContactTypeEnum::Email, 'label' => 'Email'],
            ['field' => 'pec', 'type' => ContactTypeEnum::Pec, 'label' => 'PEC'],
            ['field' => 'phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Telefono'],
            ['field' => 'phone2', 'type' => ContactTypeEnum::Phone, 'label' => null, 'primary' => false],
            ['field' => 'fax', 'type' => ContactTypeEnum::Fax, 'label' => 'Fax'],
            ['field' => 'website', 'type' => ContactTypeEnum::Website, 'label' => 'Sito web'],
        ]);
    }

    /**
     * The legal seat as the primary `legal_seat` address, then the legacy
     * `company_addresses` as non-primary addresses of their own site type.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array<int, AddressInput>
     */
    private function buildAddresses(array $record, array &$warnings): array
    {
        $candidates = [[$record, true, SiteTypeEnum::LegalSeat]];

        foreach ((array) ($record['addresses'] ?? []) as $extra) {
            $siteType = SiteTypeEnum::tryFrom((string) ($extra['site_type'] ?? ''));

            if ($siteType === null) {
                $warnings[] = "Unknown address site_type '".($extra['site_type'] ?? '')."', address skipped.";

                continue;
            }

            $candidates[] = [(array) $extra, false, $siteType];
        }

        $addresses = [];

        foreach ($candidates as [$fields, $isPrimary, $siteType]) {
            [$address, $geoWarnings] = $this->buildAddress($fields, $isPrimary, $siteType);
            array_push($warnings, ...$geoWarnings);

            if ($address !== null) {
                $addresses[] = $address;
            }
        }

        return $addresses;
    }
}
