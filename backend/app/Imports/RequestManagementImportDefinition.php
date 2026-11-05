<?php

namespace App\Imports;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowResolution;
use App\Imports\Leads\LeadDuplicateMatcher;
use App\Imports\Recognition\GeoRecognizer;
use App\Imports\Recognition\NameSplitRecognizer;
use App\Imports\Recognition\PersonNameRecognizer;
use App\Imports\RequestManagement\RequestAttributeValues;
use App\Imports\RequestManagement\RequestImportFieldCatalog;
use App\Imports\RequestManagement\RequestImportLookups;
use App\Imports\RequestManagement\RequestRowPersister;
use App\Imports\RequestManagement\RequestRowRecognizer;
use App\Imports\RequestManagement\RequestRowValidator;
use App\Imports\RequestManagement\RequestValueMappings;
use App\Models\ImportRunRow;
use App\Models\Quote;
use App\Models\User;
use RuntimeException;

/**
 * Import definition for `request-management` (spec 0348): one row = one
 * Anagrafica (new, or the existing one found) + one Opportunita + one Offerta,
 * created through the same path as Gestione Richieste — never a Lead (D-1). A
 * thin orchestrator like LeadsImportDefinition: the field catalogue, the row
 * recognition/validation, the "Valori" step and the write each live in their
 * own collaborator under `App\Imports\RequestManagement`; the Registry
 * duplicate match is the leads one (LeadDuplicateMatcher).
 */
class RequestManagementImportDefinition extends AbstractImportDefinition
{
    private const string CATEGORY_NAME_FIELD = 'product_category_name';

    public function __construct(
        private readonly RequestImportFieldCatalog $catalog,
        private readonly RequestRowValidator $rowValidator,
        private readonly LeadDuplicateMatcher $duplicateMatcher,
        private readonly RequestRowPersister $persister,
        private readonly RequestValueMappings $valueMappings,
        private readonly RequestImportLookups $lookups,
    ) {
        // The staging engine builds RequestRowRecognizer once per ROW through the
        // container: publishing this definition's lookups there is what lets all
        // the rows of one run share a single read of users/products/attributes.
        app()->instance(RequestImportLookups::class, $this->lookups);
    }

    public function domain(): string
    {
        return 'request-management';
    }

    public function modelClass(): string
    {
        return Quote::class;
    }

    /**
     * The model's own `import` ability does not exist: this domain is gated by
     * its dedicated permission.
     */
    public function authorizeImport(User $actor): bool
    {
        return $actor->can('request-management.import');
    }

    /**
     * Every row already creates Opportunita + Offerta: there is nothing to convert.
     */
    public function supportsOpportunityConversion(): bool
    {
        return false;
    }

    /**
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array
    {
        return $this->catalog->columns();
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, group: ?string, type: string}>
     */
    public function fields(): array
    {
        return $this->catalog->fields();
    }

    /**
     * @return array<int, array{id: string, label: string, required: bool, for_select_resource: ?string, multiple: bool, depends_on: ?string, default: mixed, required_unless_mapped: ?string}>
     */
    public function globalConfig(): array
    {
        return $this->catalog->globalConfig();
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    public function reviewFields(): array
    {
        return $this->catalog->reviewFields();
    }

    /**
     * @return array<int, class-string>
     */
    public function recognizers(): array
    {
        return [PersonNameRecognizer::class, NameSplitRecognizer::class, GeoRecognizer::class, RequestRowRecognizer::class];
    }

    public function supportsExtraFields(): bool
    {
        return true;
    }

    /**
     * @return array<int, ImportDedupMode>
     */
    public function dedupModes(): array
    {
        return [ImportDedupMode::CreateNew, ImportDedupMode::UpdateExisting, ImportDedupMode::Ignore, ImportDedupMode::Manual];
    }

    /**
     * @return array<int, string>
     */
    public function valueMappingFields(): array
    {
        return [RequestValueMappings::STATUS_FIELD, RequestValueMappings::SOURCE_FIELD];
    }

    /**
     * @return array{context: string, depends_on: string}
     */
    public function attributeTargets(): array
    {
        return ['context' => 'quote', 'depends_on' => 'product_category_id'];
    }

    /**
     * @return array<int, string>
     */
    public function defaultSourceFields(): array
    {
        return [self::CATEGORY_NAME_FIELD];
    }

    /**
     * D-5: a category column holding ONE distinct non-empty value that names a
     * selectable, active product line pre-fills the run's line.
     *
     * @param  array<string, array{column_key: string, values: array<int, array{value: string, count: int}>}>  $mappedColumns
     * @return array<string, mixed>
     */
    public function globalConfigDefaults(array $mappedColumns): array
    {
        return ['product_category_id' => $this->lookups->categoryIdByName(
            $mappedColumns[self::CATEGORY_NAME_FIELD]['values'] ?? [],
        )];
    }

    public function valueMappingPreview(array $mappedColumns, array $globalConfig): array
    {
        return $this->valueMappings->preview($mappedColumns, $globalConfig);
    }

    public function validateValueMappings(array $mappedColumns, array $valueMappings, array $globalConfig): array
    {
        return $this->valueMappings->errors($mappedColumns, $valueMappings, $globalConfig);
    }

    public function validateAttributeTargets(array $columnMapping, array $globalConfig): array
    {
        $categoryId = (int) ($globalConfig['product_category_id'] ?? 0);
        $attributes = $this->lookups->quoteAttributesOf($categoryId);
        $errors = [];

        foreach ($columnMapping as $columnKey => $target) {
            if (! str_starts_with((string) $target, RequestAttributeValues::TARGET_PREFIX)) {
                continue;
            }

            $attribute = $attributes[substr((string) $target, strlen(RequestAttributeValues::TARGET_PREFIX))] ?? null;

            if ($attribute === null || ! in_array($attribute['type'], RequestAttributeValues::IMPORTABLE_TYPES, true)) {
                $errors["column_mapping.{$columnKey}"][] = "The target [{$target}] is not an additional information the chosen product line can import.";
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $row  value set AFTER the recognizers ran
     * @return array<int, string>
     */
    public function validateRow(array $row, ImportRowContext $context): array
    {
        return $this->rowValidator->validate($row);
    }

    /**
     * @param  array<string, string>  $row
     */
    public function dedupKey(array $row): ?string
    {
        return null;
    }

    public function existsInDatabase(string $key): bool
    {
        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
    public function createRow(User $actor, array $row): void
    {
        throw new RuntimeException('RequestManagementImportDefinition is wizard-only: use persistRow(), not the legacy createRow().');
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    public function resolveDuplicate(array $mapped): ?int
    {
        return $this->duplicateMatcher->match($mapped)?->registryId;
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     * @return array{id: ?int, meta: ?array{registry_id: int, registry_name: string, lead_id: ?int, matched_on: array<int, string>}}
     */
    public function resolveDuplicateMatch(array $mapped, array $globalConfig): array
    {
        $match = $this->duplicateMatcher->match($mapped);

        if ($match === null) {
            return ['id' => null, 'meta' => null];
        }

        return [
            'id' => $match->registryId,
            'meta' => ['registry_id' => $match->registryId, 'registry_name' => $match->registryName, 'lead_id' => null, 'matched_on' => $match->matchedOn],
        ];
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     */
    public function persistRow(User $actor, ImportRunRow $row, array $globalConfig, string $dedupStrategy, bool $convertToOpportunity = false): void
    {
        $mode = ImportDedupMode::from($dedupStrategy);

        // Step 1: `ignore` never persists.
        if ($mode === ImportDedupMode::Ignore) {
            return;
        }

        $mapped = array_merge($row->mapped_values ?? [], $row->resolved ?? []);
        $duplicateRegistryId = $row->duplicate_of_id ?? $this->duplicateMatcher->match($mapped)?->registryId;
        $updateRegistry = $mode === ImportDedupMode::UpdateExisting;

        // Step 2: a `manual` row parked on a match is governed by the operator's
        // per-row resolution: none (or `skip`) writes nothing, `create` ignores
        // the match, `update` targets the matched Anagrafica.
        if ($mode === ImportDedupMode::Manual && $duplicateRegistryId !== null) {
            if ($row->resolution !== ImportRowResolution::Create && $row->resolution !== ImportRowResolution::Update) {
                return;
            }

            $updateRegistry = $row->resolution === ImportRowResolution::Update;
            $duplicateRegistryId = $updateRegistry ? $duplicateRegistryId : null;
        }

        // Step 3: Anagrafica + Opportunita + Offerta, one transaction, no notifications.
        $this->persister->persist($actor, $row, $globalConfig, $updateRegistry, $duplicateRegistryId);
    }
}
