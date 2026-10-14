<?php

namespace App\Migrations\Sources;

use App\DataObjects\OperationalSites\CreateOperationalSiteData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\OperationalSite;
use App\Services\OperationalSiteService;
use RuntimeException;

/**
 * `operational-sites` migration source (spec 0013 Increment 2): a site IS
 * its address (no other own field, mirrors CreateOperationalSiteData's flat
 * shape), created via OperationalSiteService. Unlike CompaniesSource, the
 * address is not optional: `street` is required (row fails, isolated per
 * AbstractMigrationSource, when missing). External geo fields are NAMES
 * (country/region/province/city) resolved to ids via MigrationGeoResolver;
 * an unresolved level is a non-fatal warning — the site is still created
 * with whatever geo did resolve.
 */
class OperationalSitesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly OperationalSiteService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'operational-sites';
    }

    public function label(): string
    {
        return 'Operational sites';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'alias', 'label' => 'Alias', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
        ];
    }

    public function endpoint(): string
    {
        return 'operational-sites';
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
        return [
            'id' => $record['id'] ?? null,
            'country' => $record['country'] ?? null,
            'region' => $record['region'] ?? null,
            'province' => $record['province'] ?? null,
            'city' => $record['city'] ?? null,
            'alias' => $record['alias'] ?? null,
            'street' => $record['street'] ?? null,
            'postal_code' => $record['postal_code'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(OperationalSite::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $line1 = trim((string) ($record['street'] ?? ''));

        if ($line1 === '') {
            throw new RuntimeException('street is required.');
        }

        $geo = $this->geoResolver->resolve(
            $record['country'] ?? null,
            $record['region'] ?? null,
            $record['province'] ?? null,
            $record['city'] ?? null,
        );

        $site = $this->service->create(
            $context->actor,
            new CreateOperationalSiteData(
                line1: $line1,
                postalCode: $record['postal_code'] ?? null,
                countryId: $geo->countryId,
                stateId: $geo->stateId,
                provinceId: $geo->provinceId,
                cityId: $geo->cityId,
                alias: ($record['alias'] ?? null) ?: null,
            ),
        );

        $site->old_id = $externalId;
        $site->save();

        return MigrationRowOutcome::created($geo->warnings, $site);
    }
}
