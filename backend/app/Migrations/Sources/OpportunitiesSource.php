<?php

namespace App\Migrations\Sources;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Sources\Concerns\MapsLegacyOperationalRecord;
use App\Migrations\Support\ExternalApiClient;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\ProductCategory;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\User;
use App\Services\OpportunityService;
use RuntimeException;

/**
 * `opportunities` migration source (spec 0189): created through
 * OpportunityService::import() (G-4) — no one-open-opportunity guard, no
 * notification, no lead — with the activity log off (G-3). The anagrafica is
 * the mandatory parent: a registry not migrated fails the row (AC-003). The
 * legacy phase (`status`) has no column here: the status is computed from
 * the quotes.
 */
class OpportunitiesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;

    private const int NAME_MAX_LENGTH = 191;

    private const string RESERVED_NOTES_LABEL = 'Note riservate:';

    public function __construct(
        ExternalApiClient $client,
        private readonly OpportunityService $service,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'opportunities';
    }

    public function label(): string
    {
        return 'Opportunities';
    }

    public function endpoint(): string
    {
        return 'opportunities';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    protected function nativeColumns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'registry_id', 'label' => 'Registry (external id)', 'type' => 'number'],
            ['id' => 'title', 'label' => 'Title', 'type' => 'string'],
            ['id' => 'supervisor_user_id', 'label' => 'Supervisor (external id)', 'type' => 'number'],
            ['id' => 'manager_user_ids', 'label' => 'Managers (external ids)', 'type' => 'string'],
            ['id' => 'product_category_ids', 'label' => 'Product categories (external ids)', 'type' => 'string'],
            ['id' => 'start_date', 'label' => 'Start date', 'type' => 'date'],
            ['id' => 'expected_close_date', 'label' => 'Expected close date', 'type' => 'date'],
            ['id' => 'estimated_value', 'label' => 'Estimated value', 'type' => 'number'],
            ['id' => 'status_label', 'label' => 'Legacy status', 'type' => 'string'],
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

        if ($this->existsByOldId(Opportunity::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $this->importRecord($externalId, $record));
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function importRecord(int|string $externalId, array $record): MigrationRowOutcome
    {
        $warnings = [];

        // Step 1: the mandatory parent anagrafica.
        $registryId = $this->resolveRegistry($record['registry_id'] ?? null);

        // Step 2: create through the import path, which never notifies, so
        // supervisor and managers travel with the payload (G-2, G-4).
        $opportunity = $this->service->import($this->buildData($registryId, $record, $warnings));

        // Step 3: old_id and the legacy timestamps.
        $this->finalizeLegacyRecord($opportunity, $externalId, $record);

        return MigrationRowOutcome::created($warnings, $opportunity);
    }

    /**
     * @throws RuntimeException the anagrafica was not migrated
     */
    private function resolveRegistry(mixed $externalRegistryId): int
    {
        $registryId = ($externalRegistryId === null || $externalRegistryId === '')
            ? null
            : $this->resolveOldId(Registry::class, $externalRegistryId);

        if ($registryId === null) {
            throw new RuntimeException("Registry (legacy id {$externalRegistryId}) not migrated.");
        }

        return $registryId;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     */
    private function buildData(int $registryId, array $record, array &$warnings): CreateOpportunityData
    {
        $productCategoryIds = $this->remapLegacyIds(ProductCategory::class, $record['product_category_ids'] ?? [], 'product_category_ids', $warnings);
        $estimatedValue = $record['estimated_value'] ?? null;

        return new CreateOpportunityData(
            registryId: $registryId,
            referentId: $this->remapLegacyId(Referent::class, $record['referent_id'] ?? null, 'referent_id', $warnings),
            commercialId: $this->remapLegacyId(Referent::class, $record['commercial_referent_id'] ?? null, 'commercial_referent_id', $warnings),
            reporterId: $this->remapLegacyId(Referent::class, $record['reporter_referent_id'] ?? null, 'reporter_referent_id', $warnings),
            supervisorId: $this->remapLegacyId(User::class, $record['supervisor_user_id'] ?? null, 'supervisor_user_id', $warnings),
            sourceId: $this->remapLegacyId(Source::class, $record['source_id'] ?? null, 'source_id', $warnings),
            leadId: null,
            managerSlots: $this->legacyManagerSlots($record['manager_user_ids'] ?? [], $warnings),
            productLines: array_map(static fn (int $id): array => ['product_category_id' => $id], $productCategoryIds),
            startDate: $this->blankToNull($record['start_date'] ?? null),
            estimatedValue: is_numeric($estimatedValue) ? (float) $estimatedValue : null,
            expectedCloseDate: $this->blankToNull($record['expected_close_date'] ?? null),
            successProbability: null,
            operationalSiteId: $this->remapLegacyId(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings),
            generalNotes: $this->generalNotes($record),
            name: $this->name($record),
        );
    }

    /**
     * The legacy title, trimmed to the column; blank -> null so the
     * automatic name applies.
     *
     * @param  array<string, mixed>  $record
     */
    private function name(array $record): ?string
    {
        $title = $this->blankToNull($record['title'] ?? null);

        return $title === null ? null : mb_substr($title, 0, self::NAME_MAX_LENGTH);
    }

    /**
     * The legacy notes, then the reserved notes under their own label.
     *
     * @param  array<string, mixed>  $record
     */
    private function generalNotes(array $record): ?string
    {
        $notes = $this->blankToNull($record['notes'] ?? null);
        $reserved = $this->blankToNull($record['reserved_notes'] ?? null);

        $parts = array_filter([
            $notes,
            $reserved === null ? null : self::RESERVED_NOTES_LABEL."\n".$reserved,
        ]);

        return $parts === [] ? null : implode("\n\n", $parts);
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
