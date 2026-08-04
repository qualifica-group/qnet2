<?php

namespace App\Imports\Leads;

use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Leads\UpdateLeadData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\Registries\UpdateRegistryData;
use App\Models\Lead;
use App\Models\Registry;
use App\Models\User;
use App\Services\Import\ImportOpportunityConvertibility;
use App\Services\LeadService;
use App\Services\RegistryService;
use RuntimeException;

/**
 * The write side of `LeadsImportDefinition::persistRow()` (spec 0033
 * AC-011/012, spec 0041 D-1): resolves/creates the Anagrafica (Registry) per
 * dedup strategy — via RegistryService + LeadProfileBuilder, never
 * duplicated anagraphic logic — then creates/updates the Lead in the
 * configured campaign via LeadService. Extracted to stay under the 300-line
 * soft limit (engineering.md §6).
 *
 * Auto-convert-to-Opportunity (spec 0045): the CREATE branch only — a
 * row that lands on the UPDATE branch never converts, mirroring
 * ConvertLeadToOpportunity's own CREATE-only contract (LeadService::create).
 * This is defense-in-depth: the confirm-step gate (ImportService::
 * confirmStaged() -> ImportOpportunityConvertibility) already blocks a
 * non-ready run, so this per-row re-check exists only so a single bad row
 * (e.g. an operator override cleared mid-flight) can never reach
 * ConvertLeadToOpportunity's throwing path.
 */
final class LeadRowPersister
{
    public function __construct(
        private readonly RegistryService $registryService,
        private readonly LeadService $leadService,
        private readonly LeadProfileBuilder $profileBuilder,
        private readonly ImportOpportunityConvertibility $convertibility,
    ) {}

    /**
     * @param  array<string, mixed>  $globalConfig
     * @param  array<string, mixed>  $mapped  field id => resolved value (after recognizers)
     * @param  array<string, mixed>  $extraValues
     */
    public function persist(
        User $actor,
        array $globalConfig,
        array $mapped,
        array $extraValues,
        bool $shouldUpdateRegistry,
        ?int $duplicateRegistryId,
        ?int $operatorOverride = null,
        ?int $siteOverride = null,
        bool $convertToOpportunity = false,
    ): void {
        $registry = $shouldUpdateRegistry && $duplicateRegistryId !== null
            ? $this->updateRegistry($actor, $duplicateRegistryId, $mapped)
            : $this->createRegistry($actor, $mapped);

        $this->attachLead(
            $actor,
            $registry,
            $globalConfig,
            $mapped,
            $extraValues,
            $shouldUpdateRegistry && $duplicateRegistryId !== null,
            $operatorOverride,
            $siteOverride,
            $convertToOpportunity,
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function createRegistry(User $actor, array $mapped): Registry
    {
        return $this->registryService->create(
            $actor,
            new CreateRegistryData(
                sourceId: null,
                sectorIds: null,
                referentIds: null,
                managerSlots: null,
                supervisorId: null,
                commercialId: null,
                reporterId: null,
                vatGroup: null,
                isSupplier: false,
                isQualifiedSupplier: false,
                agreementStatus: null,
                agreementNotes: null,
                sizeClass: null,
                employeeCount: null,
            ),
            $this->profileBuilder->build($mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function updateRegistry(User $actor, int $registryId, array $mapped): Registry
    {
        $registry = Registry::query()
            ->with(['personalData.contacts', 'personalData.addresses'])
            ->findOrFail($registryId);

        return $this->registryService->update(
            $actor,
            $registry,
            new UpdateRegistryData,
            $this->profileBuilder->buildForUpdate($registry, $mapped),
        );
    }

    /**
     * @param  array<string, mixed>  $globalConfig
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $extraValues
     */
    private function attachLead(
        User $actor,
        Registry $registry,
        array $globalConfig,
        array $mapped,
        array $extraValues,
        bool $shouldUpdate,
        ?int $operatorOverride,
        ?int $siteOverride,
        bool $convertToOpportunity,
    ): void {
        $campaignId = $this->id($globalConfig, 'campaign_id');

        if ($campaignId === null) {
            throw new RuntimeException('LeadsImportDefinition::persistRow requires a campaign_id in the global configuration.');
        }

        $sourceId = $this->id($globalConfig, 'source_id');
        // The row's own overrides (spec 0045, mirrored for site) win over the
        // run's global operator/operational site.
        $effectiveOperatorId = $operatorOverride ?? $this->id($globalConfig, 'operator_id');
        $effectiveSiteId = $siteOverride ?? $this->id($globalConfig, 'operational_site_id');
        $notes = $this->value($mapped, 'notes');
        $extraFields = $extraValues === [] ? null : $extraValues;

        $existingLead = $shouldUpdate
            ? Lead::query()->where('registry_id', $registry->id)->where('campaign_id', $campaignId)->first()
            : null;

        if ($existingLead !== null) {
            $this->leadService->update($existingLead, new UpdateLeadData(
                sourceId: $sourceId,
                sourceIdSubmitted: true,
                operationalSiteId: $effectiveSiteId,
                operationalSiteIdSubmitted: true,
                operatorId: $effectiveOperatorId,
                operatorIdSubmitted: true,
                notes: $notes,
                notesSubmitted: true,
                extraFields: $extraFields,
                extraFieldsSubmitted: true,
            ));

            return;
        }

        $this->leadService->create(new CreateLeadData(
            registryId: $registry->id,
            campaignId: $campaignId,
            operationalSiteId: $effectiveSiteId,
            sourceId: $sourceId,
            operatorId: $effectiveOperatorId,
            notes: $notes,
            extraFields: $extraFields,
            convertToOpportunity: $this->shouldConvert($convertToOpportunity, $effectiveOperatorId, $effectiveSiteId, $campaignId),
        ), $actor);
    }

    /**
     * CREATE-branch-only defense-in-depth re-check (see class docblock):
     * the confirm-step gate already enforces this at the run level, this
     * just makes sure no single row can ever reach ConvertLeadToOpportunity
     * without an operator, an operational site AND a product-line-deriving
     * campaign.
     */
    private function shouldConvert(bool $convertToOpportunity, ?int $effectiveOperatorId, ?int $operationalSiteId, int $campaignId): bool
    {
        if (! $convertToOpportunity || $effectiveOperatorId === null || $operationalSiteId === null) {
            return false;
        }

        return $this->convertibility->campaignDerivesProductLine($campaignId);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function value(array $values, string $field): ?string
    {
        $value = trim((string) ($values[$field] ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function id(array $values, string $field): ?int
    {
        $value = $values[$field] ?? null;

        return $value === null || $value === '' ? null : (int) $value;
    }
}
