<?php

namespace App\Imports\RequestManagement;

use App\DataObjects\Registries\UpdateRegistryData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Enums\WorkflowStatusGroup;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\Registry;
use App\Models\User;
use App\Services\Notifications\AssignmentNotifier;
use App\Services\Quotes\ProductOfferLineResolver;
use App\Services\RegistryService;
use App\Services\RequestManagement\RequestCreationService;
use App\Support\Import\ImportValueMappings;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;

/**
 * The write side of `RequestManagementImportDefinition::persistRow()` (spec
 * 0348, persistence_per_row): resolves the Anagrafica (a new one, or the
 * existing one updated through RegistryService/RequestProfileBuilder) and hands
 * the rest to RequestCreationService — the one path that creates Opportunita +
 * Offerta from Gestione Richieste, never re-implemented here.
 *
 * The row runs in its own transaction and with assignment notifications
 * silenced (D-4): a failure (a status outside the resolved workflow, a line
 * required by a closing status...) leaves nothing of the row behind. The
 * creation service is resolved per row ON PURPOSE: it holds workflow resolvers
 * that memoize a workflow's statuses, so a status this very run just created
 * would be invisible to a long-lived instance.
 */
final class RequestRowPersister
{
    /** @var array<int, array<string, array<string, mixed>>> run id => field => normalized value => decision */
    private array $decisions = [];

    public function __construct(
        private readonly RegistryService $registryService,
        private readonly RequestProfileBuilder $profiles,
        private readonly RequestNotesBuilder $notes,
        private readonly RequestWorkflowStatuses $statuses,
        private readonly ProductOfferLineResolver $offerLines,
        private readonly RequestImportLookups $lookups,
    ) {}

    /**
     * @param  array<string, mixed>  $globalConfig
     */
    public function persist(User $actor, ImportRunRow $row, array $globalConfig, bool $updateRegistry, ?int $duplicateRegistryId): void
    {
        AssignmentNotifier::muted(fn () => DB::transaction(function () use ($actor, $row, $globalConfig, $updateRegistry, $duplicateRegistryId): void {
            $mapped = array_merge($row->mapped_values ?? [], $row->resolved ?? []);

            // Step 1: the client — the matched Registry updated, or a new one.
            $registry = $updateRegistry && $duplicateRegistryId !== null ? $this->updatedRegistry($actor, $duplicateRegistryId, $mapped) : null;

            // Step 2: Opportunita + Offerta, through the shared creation service.
            app(RequestCreationService::class)->create($actor, $this->requestData($row, $mapped, $globalConfig, $registry));
        }));
    }

    /**
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     */
    private function requestData(ImportRunRow $row, array $mapped, array $globalConfig, ?Registry $registry): CreateRequestData
    {
        $categoryId = (int) $globalConfig['product_category_id'];
        $decisions = $this->decisionsOf($row->import_run_id);
        $operatorId = $row->operator_id ?? $mapped['operator_id'] ?? $this->id($globalConfig, 'operator_id');
        $productIds = $row->product_ids ?? $mapped['product_ids'] ?? [];

        return new CreateRequestData(
            registryId: $registry?->id,
            clientProfile: $registry === null ? $this->profiles->build($mapped) : null,
            productLines: [['product_category_id' => $categoryId]],
            sourceId: $this->sourceId($decisions, $mapped, $globalConfig),
            reporterId: isset($mapped['reporter_id']) ? (int) $mapped['reporter_id'] : null,
            managerSlots: $operatorId === null ? null : $this->operatorSlots((int) $operatorId),
            operationalSiteId: $this->operationalSiteId($row, $operatorId, $globalConfig),
            nextCallbackAt: $mapped['next_callback_at'] ?? null,
            generalNotes: $this->notes->build($mapped, $row->extra_values ?? []),
            attributeValues: ($mapped['attribute_values'] ?? []) === [] ? null : $mapped['attribute_values'],
            offerLines: $productIds === [] ? null : $this->offerLines->resolve(array_map(intval(...), $productIds)),
            workflowStatusId: $this->statusId($decisions, $mapped, $categoryId, $this->id($globalConfig, 'source_id')),
        );
    }

    /**
     * The Sede of the request (user directive 2026-11-03): the row's own override
     * from the review step, else the operator's primary site, else the run's
     * optional fallback. Left null, RequestCreationService defaults to the actor's.
     *
     * @param  array<string, mixed>  $globalConfig
     */
    private function operationalSiteId(ImportRunRow $row, int|string|null $operatorId, array $globalConfig): ?int
    {
        return $row->operational_site_id
            ?? ($operatorId === null ? null : $this->lookups->primarySiteIdOf((int) $operatorId))
            ?? $this->id($globalConfig, 'operational_site_id');
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function updatedRegistry(User $actor, int $registryId, array $mapped): Registry
    {
        $registry = Registry::query()->with(['personalData.contacts', 'personalData.addresses'])->findOrFail($registryId);

        return $this->registryService->update($actor, $registry, new UpdateRegistryData, $this->profiles->build($mapped, $registry));
    }

    /**
     * The status the user mapped the row's file value to: an existing one, or a
     * new one created now — once per (workflow, name), by whichever row asks first.
     *
     * @param  array<string, array<string, mixed>>  $decisions
     * @param  array<string, mixed>  $mapped
     */
    private function statusId(array $decisions, array $mapped, int $categoryId, ?int $sourceId): ?int
    {
        $decision = $this->decisionFor($decisions, RequestValueMappings::STATUS_FIELD, $mapped);

        if (isset($decision['id'])) {
            return (int) $decision['id'];
        }

        if (! isset($decision['create'])) {
            return null;
        }

        return $this->statuses->findOrCreate(
            $categoryId,
            $sourceId,
            (string) $decision['create']['name'],
            WorkflowStatusGroup::from((string) $decision['create']['group']),
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $decisions
     * @param  array<string, mixed>  $mapped
     * @param  array<string, mixed>  $globalConfig
     */
    private function sourceId(array $decisions, array $mapped, array $globalConfig): ?int
    {
        $decision = $this->decisionFor($decisions, RequestValueMappings::SOURCE_FIELD, $mapped);

        return isset($decision['id']) ? (int) $decision['id'] : $this->id($globalConfig, 'source_id');
    }

    /**
     * @param  array<string, array<string, mixed>>  $decisions
     * @param  array<string, mixed>  $mapped
     * @return array<string, mixed>|null
     */
    private function decisionFor(array $decisions, string $field, array $mapped): ?array
    {
        if (! array_key_exists($field, $mapped)) {
            return null;
        }

        return $decisions[$field][ImportValueMappings::normalize((string) $mapped[$field])] ?? null;
    }

    /**
     * The run's value_mappings keyed by normalized file value, read once per run.
     *
     * @return array<string, array<string, mixed>>
     */
    private function decisionsOf(int $runId): array
    {
        if (! isset($this->decisions[$runId])) {
            $this->decisions[$runId] = [];

            foreach (ImportRun::query()->findOrFail($runId)->value_mappings ?? [] as $field => $byValue) {
                foreach ((array) $byValue as $value => $decision) {
                    $this->decisions[$runId][$field][ImportValueMappings::normalize((string) $value)] = $decision;
                }
            }
        }

        return $this->decisions[$runId];
    }

    /**
     * @return array<int, int|null>
     */
    private function operatorSlots(int $operatorId): array
    {
        $slots = array_fill(0, ManagerPositions::OPERATOR, null);
        $slots[ManagerPositions::OPERATOR - 1] = $operatorId;

        return $slots;
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
