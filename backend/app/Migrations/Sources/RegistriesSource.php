<?php

namespace App\Migrations\Sources;

use App\DataObjects\Registries\CreateRegistryData;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\MapsLegacyRegistryProfile;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Migrations\Support\PersonNameSplitter;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\User;
use App\Services\RegistryService;
use App\Support\ManagerPositions;
use App\Support\PositionalPivotSync;
use BackedEnum;
use RuntimeException;

/**
 * `registries` migration source (spec 0189): the legacy `companies` become
 * anagrafiche through RegistryService::create() — card, contacts and
 * addresses via MapsLegacyRegistryProfile (G-11). Supervisor and managers are
 * written AFTER the create, never passed to the service, so no assignment
 * notification is sent (G-2); the whole row runs with the activity log off
 * (G-3).
 */
class RegistriesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;
    use MapsLegacyRegistryProfile;

    public function __construct(
        ExternalApiClient $client,
        private readonly RegistryService $service,
        private readonly MigrationGeoResolver $geoResolver,
        private readonly PersonNameSplitter $nameSplitter,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'registries';
    }

    public function label(): string
    {
        return 'Registries';
    }

    public function endpoint(): string
    {
        return 'registries';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'is_private', 'label' => 'Private person', 'type' => 'boolean'],
            ['id' => 'company_name', 'label' => 'Company name', 'type' => 'string'],
            ['id' => 'first_name', 'label' => 'First name', 'type' => 'string'],
            ['id' => 'last_name', 'label' => 'Last name', 'type' => 'string'],
            ['id' => 'tax_code', 'label' => 'Tax code', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'is_supplier', 'label' => 'Supplier', 'type' => 'boolean'],
            ['id' => 'source_label', 'label' => 'Source', 'type' => 'string'],
            ['id' => 'sectors', 'label' => 'Sectors', 'type' => 'string'],
            ['id' => 'manager_user_ids', 'label' => 'Managers (external ids)', 'type' => 'string'],
            ['id' => 'email', 'label' => 'Email', 'type' => 'string'],
            ['id' => 'phone', 'label' => 'Phone', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'addresses', 'label' => 'Additional addresses', 'type' => 'number'],
            ['id' => 'created_at', 'label' => 'Created at', 'type' => 'date'],
        ];
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
        return $this->legacyPreviewCells($record);
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Registry::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($context, $externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(MigrationImportContext $context, int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [];

        // Step 1: the card, its contacts and addresses (a nameless card fails the row).
        $profile = $this->buildProfile($record, $warnings);

        // Step 2: create through the domain service, without the notified roles (G-2).
        $registry = $this->service->create($context->actor, $this->buildRegistryData($record, $warnings), $profile);

        // Step 3: managers written directly on the pivot, so nobody is notified.
        $managerSlots = $this->legacyManagerSlots($record['manager_user_ids'] ?? [], $warnings);
        PositionalPivotSync::sync($registry->managers(), ManagerPositions::syncMap($managerSlots));

        // Step 4: supervisor, old_id and the legacy timestamps.
        $this->finalizeLegacyRecord($registry, $externalId, $record, [
            'supervisor_id' => $this->remapLegacyId(User::class, $record['supervisor_user_id'] ?? null, 'supervisor_user_id', $warnings),
        ]);

        return MigrationRowOutcome::created($warnings, $registry);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function buildRegistryData(array $record, array &$warnings): CreateRegistryData
    {
        return new CreateRegistryData(
            sourceId: $this->resolveSource($record, $warnings),
            sectorIds: $this->resolveSectors($record['sectors'] ?? [], $warnings),
            referentIds: $this->remapLegacyIds(Referent::class, $record['referent_ids'] ?? [], 'referent_ids', $warnings),
            managerSlots: null,
            supervisorId: null,
            commercialId: $this->remapLegacyId(Referent::class, $record['commercial_referent_id'] ?? null, 'commercial_referent_id', $warnings),
            reporterId: $this->remapLegacyId(Referent::class, $record['reporter_referent_id'] ?? null, 'reporter_referent_id', $warnings),
            vatGroup: $this->blankToNull($record['vat_group'] ?? null),
            isSupplier: (bool) ($record['is_supplier'] ?? false),
            isQualifiedSupplier: (bool) ($record['is_qualified_supplier'] ?? false),
            agreementStatus: $this->enumValue(AgreementStatusEnum::class, $record['agreement_status'] ?? null, 'agreement_status', $warnings),
            agreementNotes: $this->blankToNull($record['agreement_notes'] ?? null),
            sizeClass: $this->enumValue(SizeClassEnum::class, $record['size_class'] ?? null, 'size_class', $warnings),
            employeeCount: $this->blankToInt($record['employee_count'] ?? null),
        );
    }

    /**
     * An enum value from the legacy text: blank -> null, unknown -> null with
     * a warning.
     *
     * @param  class-string<BackedEnum>  $enumClass
     * @param  array<int, string>  $warnings
     */
    private function enumValue(string $enumClass, mixed $raw, string $field, array &$warnings): ?string
    {
        $value = $this->blankToNull($raw);

        if ($value === null) {
            return null;
        }

        $case = $enumClass::tryFrom($value);

        if ($case === null) {
            $warnings[] = "Unknown {$field} '{$value}', left blank.";
        }

        return $case?->value;
    }

    /**
     * The legacy API resolves the source from free text; a text it could not
     * resolve is reported, never silently dropped.
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function resolveSource(array $record, array &$warnings): ?int
    {
        $label = $this->blankToNull($record['source_label'] ?? null);

        if (($record['source_id'] ?? null) === null && $label !== null) {
            $warnings[] = "Unresolved source '{$label}'.";

            return null;
        }

        return $this->remapLegacyId(Source::class, $record['source_id'] ?? null, 'source_id', $warnings);
    }

    /**
     * Each legacy sector token matched by exact name among the migrated
     * sectors (names are not unique: the oldest wins).
     *
     * @param  array<int, string>  $warnings
     * @return array<int, int>
     */
    private function resolveSectors(mixed $names, array &$warnings): array
    {
        $ids = [];

        foreach ((array) $names as $name) {
            $name = trim((string) $name);

            if ($name === '') {
                continue;
            }

            $id = Sector::query()->whereNotNull('old_id')->where('name', $name)->orderBy('id')->value('id');

            if ($id === null) {
                $warnings[] = "Unresolved sector '{$name}'.";

                continue;
            }

            $ids[$id] = (int) $id;
        }

        return array_values($ids);
    }
}
