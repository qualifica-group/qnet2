<?php

namespace App\Migrations\Sources;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationDependency;
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
use App\Services\ProductLines\ProductLineWriter;
use RuntimeException;

/**
 * `opportunities` migration source (spec 0189): created through
 * OpportunityService::import() (G-4) — no one-open-opportunity guard, no
 * notification, no lead — with the activity log off (G-3). The anagrafica is
 * the mandatory parent: a registry not migrated fails the row (AC-003). The
 * legacy phase (`status`) has no column here: the status is computed from
 * the quotes.
 *
 * G-12: the legacy `product_category_ids` are the categories of the products
 * on the opportunity's quotes' lines (the same rows
 * OpportunityProductLineCoverage looks for when those quotes are imported),
 * or its own categories when its quotes carry none. A re-import fills them
 * in on an already imported opportunity that still has no product line
 * (never overwriting existing ones).
 *
 * The legacy sends the composed `general_notes` and, apart, the Info
 * preliminari card blocks (`preliminary_info_blocks`): a re-import appends
 * the blocks an already imported opportunity still lacks.
 */
class OpportunitiesSource extends AbstractMigrationSource
{
    use MapsLegacyOperationalRecord;

    private const int NAME_MAX_LENGTH = 191;

    private const string NOTES_SEPARATOR = "\n\n";

    private const string BACKFILLED_PRODUCT_LINES_NOTE = 'Missing product categories back-filled.';

    private const string BACKFILLED_NOTES_NOTE = 'Missing preliminary info notes back-filled.';

    public function __construct(
        ExternalApiClient $client,
        private readonly OpportunityService $service,
        private readonly ProductLineWriter $productLineWriter,
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

    /**
     * {@inheritDoc}
     */
    public function dependencies(): array
    {
        return [
            MigrationDependency::required('registries'),
            MigrationDependency::optional('users'),
            MigrationDependency::optional('referents'),
            MigrationDependency::optional('sources'),
            MigrationDependency::optional('product-categories'),
            MigrationDependency::optional('operational-sites'),
        ];
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

        $existing = Opportunity::query()->where('old_id', $externalId)->first();

        return activity()->withoutLogs(fn (): MigrationRowOutcome => $existing === null
            ? $this->importRecord($externalId, $record)
            : $this->backfillExisting($existing, $record));
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
     * Skipped row (idempotent re-import, AC-002) that still fills in what an
     * opportunity was imported without. Warnings are reported only alongside
     * a write: a row with nothing to fill stays a silent skip on every re-run.
     *
     * @param  array<string, mixed>  $record
     */
    private function backfillExisting(Opportunity $opportunity, array $record): MigrationRowOutcome
    {
        return MigrationRowOutcome::skipped([
            ...$this->backfillProductLines($opportunity, $record),
            ...$this->backfillPreliminaryInfoNotes($opportunity, $record),
        ]);
    }

    /**
     * The product lines of an opportunity imported without any (G-12).
     *
     * @param  array<string, mixed>  $record
     * @return array<int, string> the report entries, empty when nothing was written
     */
    private function backfillProductLines(Opportunity $opportunity, array $record): array
    {
        if ($opportunity->productLines()->exists()) {
            return [];
        }

        $warnings = [];
        $productLines = $this->productLines($record, $warnings);

        if ($productLines === []) {
            return [];
        }

        $this->productLineWriter->sync($opportunity, $productLines);

        return [...$warnings, self::BACKFILLED_PRODUCT_LINES_NOTE];
    }

    /**
     * The Info preliminari notes the general notes still lack, appended
     * without touching the legacy timestamps.
     *
     * @param  array<string, mixed>  $record
     * @return array<int, string> the report entries, empty when nothing was written
     */
    private function backfillPreliminaryInfoNotes(Opportunity $opportunity, array $record): array
    {
        $current = $this->blankToNull($opportunity->general_notes);
        $missing = array_filter(
            array_map(strval(...), (array) ($record['preliminary_info_blocks'] ?? [])),
            static fn (string $block): bool => $block !== '' && ! str_contains((string) $current, $block),
        );

        if ($missing === []) {
            return [];
        }

        $notes = implode(self::NOTES_SEPARATOR, array_filter([$current, ...$missing]));

        $opportunity->timestamps = false;
        $opportunity->forceFill(['general_notes' => $notes])->saveQuietly();

        return [self::BACKFILLED_NOTES_NOTE];
    }

    /**
     * A product category not migrated fails the opportunity (user decision
     * 2026-10-14: no line is dropped silently).
     *
     * @param  array<string, mixed>  $record
     * @param  array<int, string>  $warnings
     * @return array<int, array{product_category_id: int}>
     */
    private function productLines(array $record, array &$warnings): array
    {
        $externalIds = array_values(array_unique(array_filter(array_map(intval(...), (array) ($record['product_category_ids'] ?? [])))));
        $categoryIds = $this->remapLegacyIds(ProductCategory::class, $externalIds, 'product_category_ids', $warnings);

        if (count($categoryIds) !== count($externalIds)) {
            $missing = implode(', ', array_diff($externalIds, ProductCategory::query()->whereIn('old_id', $externalIds)->pluck('old_id')->map(intval(...))->all()));

            throw new RuntimeException("Product category (legacy id {$missing}) not migrated; the opportunity is not imported.");
        }

        return array_map(static fn (int $id): array => ['product_category_id' => $id], $categoryIds);
    }

    /**
     * @throws RuntimeException the anagrafica was not migrated
     */
    private function resolveRegistry(mixed $externalRegistryId): int
    {
        $registryId = ($externalRegistryId === null || $externalRegistryId === '')
            ? null
            : $this->resolveOldId(Registry::class, $externalRegistryId);

        if ($externalRegistryId === null || $externalRegistryId === '') {
            throw new RuntimeException('The opportunity has no customer in the legacy; it is not imported.');
        }

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
            productLines: $this->productLines($record, $warnings),
            startDate: $this->blankToNull($record['start_date'] ?? null),
            estimatedValue: is_numeric($estimatedValue) ? (float) $estimatedValue : null,
            expectedCloseDate: $this->blankToNull($record['expected_close_date'] ?? null),
            successProbability: null,
            operationalSiteId: $this->remapLegacyId(OperationalSite::class, $record['operational_site_id'] ?? null, 'operational_site_id', $warnings),
            generalNotes: $this->blankToNull($record['general_notes'] ?? null),
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

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
