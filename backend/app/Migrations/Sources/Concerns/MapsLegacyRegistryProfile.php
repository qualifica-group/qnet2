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
use App\Migrations\Support\PersonNameSplitter;
use RuntimeException;

/**
 * The personal-data profile of a legacy `companies` record (spec 0189, G-11):
 * person card when `is_private`, company card otherwise, cleaned fiscal codes,
 * contacts and addresses. Split out of RegistriesSource, which keeps the
 * orchestration and the relational remaps.
 *
 * @phpstan-require-extends RegistriesSource
 *
 * @property-read MigrationGeoResolver $geoResolver
 * @property-read PersonNameSplitter $nameSplitter
 */
trait MapsLegacyRegistryProfile
{
    use MapsExternalProfileRecord;

    private const string WEBSITE_SCHEME = 'https://';

    /** Fewer digits than this cannot be an Italian or EU VAT number: a legacy placeholder. */
    private const int VAT_MIN_DIGITS = 9;

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     *
     * @throws RuntimeException the card would have no name
     */
    private function buildProfile(array $record, array &$warnings): ProfileData
    {
        $card = $this->buildCard($record, $warnings);

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
     * A private customer's first name and surname are re-split on the tax
     * code: the legacy heuristic often inverts them.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function buildCard(array $record, array &$warnings): CreatePersonalData
    {
        $isPrivate = (bool) ($record['is_private'] ?? false);
        $taxCode = $this->blankToNull($record['tax_code'] ?? null);
        [$firstName, $lastName] = $isPrivate
            ? $this->nameSplitter->split($this->blankToNull($record['first_name'] ?? null), $this->blankToNull($record['last_name'] ?? null), $taxCode)
            : [null, null];

        return new CreatePersonalData(
            type: $isPrivate ? PersonalDataTypeEnum::Individual : PersonalDataTypeEnum::Company,
            firstName: $this->blankToNull($firstName),
            lastName: $this->blankToNull($lastName),
            companyName: $isPrivate ? null : $this->blankToNull($record['company_name'] ?? null),
            taxCode: $taxCode === null ? null : mb_strtoupper($taxCode),
            vatNumber: $this->cleanVatNumber($record['vat_number'] ?? null, $warnings),
            sdiCode: $this->blankToNull($record['sdi_code'] ?? null),
        );
    }

    /**
     * The `IT` prefix is dropped; a placeholder (`0`, `1`, `.`, only zeros,
     * fewer than VAT_MIN_DIGITS digits) becomes null with a warning.
     *
     * @param  array<int, string>  $warnings
     */
    private function cleanVatNumber(mixed $raw, array &$warnings): ?string
    {
        $value = mb_strtoupper(trim((string) ($raw ?? '')));

        if ($value === '') {
            return null;
        }

        $value = trim((string) preg_replace('/^IT/', '', $value));
        $digits = (string) preg_replace('/\D/', '', $value);

        if (strlen($digits) < self::VAT_MIN_DIGITS || trim($digits, '0') === '') {
            $warnings[] = "Placeholder vat_number '{$raw}' discarded.";

            return null;
        }

        return $value;
    }

    /**
     * Email, PEC, phone, second phone (non-primary, so the main phone stays
     * the primary one), fax and website (an `https://` scheme is prepended
     * when the legacy value has none).
     *
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        $website = $this->blankToNull($record['website'] ?? null);

        if ($website !== null && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $website)) {
            $record['website'] = self::WEBSITE_SCHEME.$website;
        }

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
            $siteType = SiteTypeEnum::tryFrom((string) ($extra['site_type'] ?? '')) ?? SiteTypeEnum::OperationalSite;
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
