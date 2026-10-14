<?php

namespace App\Migrations\Sources;

use App\CustomFields\CustomFieldProvider;
use App\CustomFields\CustomFieldWriter;
use App\DataObjects\Registries\CreateRegistryData;
use App\Enums\AgreementStatusEnum;
use App\Enums\SizeClassEnum;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Sources\Concerns\MapsLegacyRegistryProfile;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Sector;
use App\Models\Source;
use App\Models\Tag;
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
 * (G-3). The legacy `company_tags` land in the `tags` custom field (a relation
 * many -> tags provisioned by the client template), the only place a registry
 * keeps its tags.
 */
class RegistriesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;
    use MapsLegacyRegistryProfile;

    private const string TAGS_CUSTOM_FIELD = 'tags';

    public function __construct(
        ExternalApiClient $client,
        private readonly RegistryService $service,
        private readonly MigrationGeoResolver $geoResolver,
        private readonly CustomFieldProvider $customFields,
        private readonly CustomFieldWriter $customFieldWriter,
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

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::optional('users'),
            MigrationDependency::optional('referents'),
            MigrationDependency::optional('sources'),
            MigrationDependency::optional('sectors'),
            MigrationDependency::optional('tags'),
        ];
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
            ['id' => 'sector_ids', 'label' => 'Sectors (external ids)', 'type' => 'string'],
            ['id' => 'manager_user_ids', 'label' => 'Managers (external ids)', 'type' => 'string'],
            ['id' => 'tag_ids', 'label' => 'Tags (external ids)', 'type' => 'string'],
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

        // Step 5: the legacy tags, into the tags custom field.
        $this->writeTags($registry, $record['tag_ids'] ?? [], $warnings);

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
            sectorIds: $this->remapLegacyIds(Sector::class, $record['sector_ids'] ?? [], 'sector_ids', $warnings),
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
     * Remapped tag ids written to the `tags` custom field. Without that
     * definition (client template not seeded) the tags are reported, never
     * silently dropped.
     *
     * @param  array<int, string>  $warnings
     */
    private function writeTags(Registry $registry, mixed $externalTagIds, array &$warnings): void
    {
        $tagIds = $this->remapLegacyIds(Tag::class, $externalTagIds, 'tag_ids', $warnings);

        if ($tagIds === []) {
            return;
        }

        if ($this->customFields->definitionsFor($this->key())->doesntContain('key', self::TAGS_CUSTOM_FIELD)) {
            $warnings[] = 'Tags not imported: the registries "'.self::TAGS_CUSTOM_FIELD.'" custom field is not defined.';

            return;
        }

        $this->customFieldWriter->write($registry, $this->key(), [self::TAGS_CUSTOM_FIELD => $tagIds]);
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
}
