<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\PersonalData\CreateAddress;
use App\DataObjects\PersonalData\CreateContact;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Enums\ContactTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\Support\MigrationGeoResolver;
use Illuminate\Support\Facades\Validator;

/**
 * Field-mapping helpers shared by every migration source whose target owns a
 * personal-data card (morph `personable`) — currently UsersSource (via
 * MapsExternalUserRecord) and ReferentsSource. Translates the external geo
 * NAMES and contact channels of one raw record into the qnet value objects
 * (AddressInput/ContactInput) consumed by the profile write, so both sources
 * build "contacts and addresses" the exact same way (spec 0013, ADR 0012).
 *
 * Owner-specific concerns (roles/employment for users, type/scope for
 * referents) stay on the using source; only the truly shared, field-name-
 * agnostic mapping lives here.
 *
 * @phpstan-require-extends AbstractMigrationSource
 *
 * @property-read MigrationGeoResolver $geoResolver
 */
trait MapsExternalProfileRecord
{
    /**
     * Build the record's primary address from the external geo NAMES (same
     * approach as CompaniesSource/OperationalSitesSource), only when at least a
     * street or a city was supplied; a street-less city falls back to being the
     * address line itself. An unresolved geo level is a non-fatal warning — the
     * address is still created with whatever resolved.
     *
     * @param  array<string, mixed>  $record
     * @return array{0: ?AddressInput, 1: array<int, string>}
     */
    private function buildAddress(array $record): array
    {
        $line1 = trim((string) ($record['street'] ?? ''));
        $city = trim((string) ($record['city'] ?? ''));

        if ($line1 === '' && $city === '') {
            return [null, []];
        }

        $geo = $this->geoResolver->resolve(
            $record['country'] ?? null,
            $record['region'] ?? null,
            $record['province'] ?? null,
            $record['city'] ?? null,
        );

        $address = new AddressInput(
            id: null,
            data: new CreateAddress(
                line1: $line1 !== '' ? $line1 : $city,
                postalCode: $this->blankToNull($record['postal_code'] ?? null),
                cityId: $geo->cityId,
                provinceId: $geo->provinceId,
                stateId: $geo->stateId,
                countryId: $geo->countryId,
                isPrimary: true,
            ),
        );

        return [$address, $geo->warnings];
    }

    /**
     * Build the record's contact channels from a declared candidate list, each
     * entry `{field, type, label}`: the external field is read, and a
     * present-but-invalid value (fails the type's own `valueRules()`) is skipped
     * with a non-fatal warning rather than failing the whole row.
     *
     * Every migrated contact is flagged primary: the "at most one primary per
     * owner + type" invariant (ContactService) then keeps the last of each type,
     * so distinct-type channels all stay primary while same-type duplicates are
     * reconciled to one.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, array{field: string, type: ContactTypeEnum, label: ?string}>  $candidates
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContactInputs(array $record, array $candidates): array
    {
        $inputs = [];
        $warnings = [];

        foreach ($candidates as $candidate) {
            $value = trim((string) ($record[$candidate['field']] ?? ''));

            if ($value === '') {
                continue;
            }

            if (! $this->isValidContactValue($candidate['type'], $value)) {
                $warnings[] = "Invalid {$candidate['field']} value, skipped.";

                continue;
            }

            $inputs[] = new ContactInput(id: null, data: new CreateContact(
                type: $candidate['type'],
                value: $value,
                label: $candidate['label'],
                isPrimary: true,
            ));
        }

        return [$inputs, $warnings];
    }

    private function isValidContactValue(ContactTypeEnum $type, string $value): bool
    {
        return Validator::make(['value' => $value], ['value' => $type->valueRules()])->passes();
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function blankToInt(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }
}
