<?php

namespace App\Imports;

use App\Enums\ImportDedupMode;
use App\Enums\ImportRowResolution;
use App\Imports\Leads\LeadDuplicateMatcher;
use App\Imports\Leads\LeadImportFieldCatalog;
use App\Imports\Leads\LeadRowPersister;
use App\Imports\Leads\LeadRowValidator;
use App\Imports\Recognition\CampaignRecognizer;
use App\Imports\Recognition\GeoRecognizer;
use App\Imports\Recognition\NameSplitRecognizer;
use App\Models\ImportRunRow;
use App\Models\Lead;
use App\Models\User;
use RuntimeException;

/**
 * Import definition for `leads` (spec 0033, spec 0041 D-1): the first — and
 * only — domain riding the mapping-driven wizard. One row = one Registry
 * anagraphic (identity + contacts + address) + one Lead tying it to the
 * campaign/status/source/operator picked once in the configuration step
 * (globalConfig()).
 *
 * A thin orchestrator: the field/global catalogue (LeadImportFieldCatalog),
 * the row's own-field validation (LeadRowValidator), the duplicate match
 * (LeadDuplicateMatcher) and the actual write (LeadRowPersister — delegating
 * to RegistryService/LeadService, never duplicated anagraphic logic) each
 * live in their own collaborator under `App\Imports\Leads`, keeping this
 * class under the 300-line soft limit (engineering.md §6).
 */
class LeadsImportDefinition extends AbstractImportDefinition
{
    public function __construct(
        private readonly LeadImportFieldCatalog $catalog,
        private readonly LeadRowValidator $rowValidator,
        private readonly LeadDuplicateMatcher $duplicateMatcher,
        private readonly LeadRowPersister $persister,
    ) {}

    public function domain(): string
    {
        return 'leads';
    }

    public function modelClass(): string
    {
        return Lead::class;
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
     * @return array<int, string>
     */
    public function requiredForCreation(): array
    {
        return $this->catalog->requiredForCreation();
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
        return [CampaignRecognizer::class, NameSplitRecognizer::class, GeoRecognizer::class];
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
        return [
            ImportDedupMode::CreateNew,
            ImportDedupMode::UpdateExisting,
            ImportDedupMode::Ignore,
            ImportDedupMode::Manual,
        ];
    }

    /**
     * $row is the field-id-keyed value set AFTER recognizers ran (mapped
     * values merged with NameSplitRecognizer/GeoRecognizer output — the same
     * shape resolveDuplicate() receives).
     *
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    public function validateRow(array $row, ImportRowContext $context): array
    {
        return $this->rowValidator->validate($row);
    }

    /**
     * No legacy natural key: dedup for `leads` is entirely driven by
     * resolveDuplicate() (Registry contact match) + the chosen strategy, not
     * by the pre-0033 single-column dedupKey()/existsInDatabase() pair.
     *
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
     * Unreachable via the unified wizard flow (StageImportJob/ProcessImportJob
     * call persistRow(), never createRow(), for a definition with a non-empty
     * dedupModes()/resolveDuplicate() override). Kept only to satisfy the
     * interface: a Lead structurally cannot be created without a campaign,
     * which the legacy single-row create-only flow has no way to supply (it
     * only exists in the wizard's globalConfig()).
     *
     * @param  array<string, string>  $row
     */
    public function createRow(User $actor, array $row): void
    {
        throw new RuntimeException('LeadsImportDefinition is wizard-only: use persistRow(), not the legacy createRow().');
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
            'meta' => [
                'registry_id' => $match->registryId,
                'registry_name' => $match->registryName,
                'lead_id' => $this->duplicateMatcher->existingLeadId($match->registryId, $mapped, $globalConfig),
                'matched_on' => $match->matchedOn,
            ],
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
        $shouldUpdateRegistry = $mode === ImportDedupMode::UpdateExisting;

        // Step 2: a `manual` row parked on a match is governed by the
        // operator's per-row `resolution` (spec 0036), not the run's global
        // strategy: no resolution (or `skip`) never writes — the previous
        // defensive no-op, now explicit; `create` forces a brand-new
        // Registry+Lead, ignoring the match; `update` targets the matched
        // Registry's own Lead.
        if ($mode === ImportDedupMode::Manual && $duplicateRegistryId !== null) {
            if ($row->resolution !== ImportRowResolution::Create && $row->resolution !== ImportRowResolution::Update) {
                return;
            }

            $shouldUpdateRegistry = $row->resolution === ImportRowResolution::Update;
            $duplicateRegistryId = $shouldUpdateRegistry ? $duplicateRegistryId : null;
        }

        // Step 3: update the matched Registry, or create a new one
        // (create_new always inserts; update_existing with no match falls
        // back to create_new, per spec), then attach the Lead to the
        // configured campaign.
        $this->persister->persist(
            $actor,
            $globalConfig,
            $mapped,
            $row->extra_values ?? [],
            $shouldUpdateRegistry,
            $duplicateRegistryId,
            $row->operator_id,
            $row->operational_site_id,
            $convertToOpportunity,
            $row->product_ids,
        );
    }
}
