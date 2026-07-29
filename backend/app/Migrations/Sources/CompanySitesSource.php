<?php

namespace App\Migrations\Sources;

use App\DataObjects\CompanySites\CreateCompanySiteData;
use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\ContactInput;
use App\DataObjects\Users\ProfileData;
use App\Enums\ContactTypeEnum;
use App\Enums\PersonalDataTypeEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsExternalProfileRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\Company;
use App\Models\CompanySite;
use App\Services\CompanySiteService;
use RuntimeException;

/**
 * Imports the "Società Sedi" resource through CompanySiteService. The external
 * company reference is remapped through Company.old_id; profile contacts and
 * the single address use the same mapping shared by the other profile sources.
 */
class CompanySitesSource extends AbstractMigrationSource
{
    use MapsExternalProfileRecord;

    public function __construct(
        ExternalApiClient $client,
        private readonly CompanySiteService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'company-sites';
    }

    public function label(): string
    {
        return 'Company sites';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'company_id', 'label' => 'Company (external id)', 'type' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'string'],
            ['id' => 'notes', 'label' => 'Notes', 'type' => 'string'],
            ['id' => 'fiscal_code', 'label' => 'Fiscal code', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'sdi_code', 'label' => 'SDI code', 'type' => 'string'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'pec', 'label' => 'PEC', 'type' => 'string'],
            ['id' => 'phone', 'label' => 'Phone', 'type' => 'string'],
            ['id' => 'mobile', 'label' => 'Mobile', 'type' => 'string'],
            ['id' => 'fax', 'label' => 'Fax', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'company-sites';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapNativeRow(array $record): array
    {
        $row = [];

        foreach ($this->nativeColumns() as $column) {
            $row[$column['id']] = $record[$column['id']] ?? null;
        }

        return $row;
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(CompanySite::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $name = trim((string) ($record['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('name is required.');
        }

        $warnings = [];
        $companyId = $this->resolveCompany($record['company_id'] ?? null, $warnings);
        [$address, $addressWarnings] = $this->buildAddress($record);
        [$contacts, $contactWarnings] = $this->buildContacts($record);
        array_push($warnings, ...$addressWarnings, ...$contactWarnings);

        $site = $this->service->create(
            $context->actor,
            new CreateCompanySiteData(
                name: $name,
                notes: $this->blankToNull($record['notes'] ?? null),
                companyId: $companyId,
            ),
            new ProfileData(
                card: new CreatePersonalData(
                    type: PersonalDataTypeEnum::Company,
                    companyName: $name,
                    taxCode: $this->blankToNull($record['fiscal_code'] ?? null),
                    vatNumber: $this->blankToNull($record['vat_number'] ?? null),
                    sdiCode: $this->blankToNull($record['sdi_code'] ?? null),
                ),
                contacts: $contacts === [] ? null : $contacts,
                addresses: $address === null ? null : [$address],
            ),
        );

        $site->old_id = $externalId;
        $site->save();

        return MigrationRowOutcome::created($warnings, $site);
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function resolveCompany(mixed $externalId, array &$warnings): ?int
    {
        if ($externalId === null || $externalId === '') {
            return null;
        }

        $companyId = $this->resolveOldId(Company::class, $externalId);

        if ($companyId === null) {
            $warnings[] = "Unresolved company_id (external id {$externalId}).";
        }

        return $companyId;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array{0: array<int, ContactInput>, 1: array<int, string>}
     */
    private function buildContacts(array $record): array
    {
        return $this->buildContactInputs($record, [
            ['field' => 'email', 'type' => ContactTypeEnum::Email, 'label' => 'Email'],
            ['field' => 'pec', 'type' => ContactTypeEnum::Pec, 'label' => 'PEC'],
            ['field' => 'phone', 'type' => ContactTypeEnum::Phone, 'label' => 'Phone'],
            ['field' => 'mobile', 'type' => ContactTypeEnum::Mobile, 'label' => 'Mobile'],
            ['field' => 'fax', 'type' => ContactTypeEnum::Fax, 'label' => 'Fax'],
        ]);
    }
}
