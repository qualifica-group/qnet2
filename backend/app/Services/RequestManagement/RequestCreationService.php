<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Models\Opportunity;
use App\Models\Registry;
use App\Models\User;
use App\Services\OpportunityService;
use App\Services\RegistryService;
use Illuminate\Support\Facades\DB;

/**
 * Creation entry point for the request-management work panel (spec 0057,
 * POST /api/request-management). The record IS an Opportunity (D-1): a
 * dedicated class rather than growing RequestManagementService (which owns
 * the panel's read/update lifecycle, a distinct concern — SRP, file-size
 * split per engineering.md §6).
 *
 * The client anagraphic block (D-2) either points at an existing Registry
 * (`registry_id`, left untouched) or creates a brand-new one through
 * RegistryService::create() — this class never MODIFIES an existing
 * Registry's anagrafica, that stays the work panel's own competence. The
 * Opportunity itself is created through the SAME OpportunityService the
 * opportunities form uses, so the `OPP_{id}` name derivation (spec 0057,
 * D-5) and the product-lines sync are never duplicated here.
 */
final class RequestCreationService
{
    public function __construct(
        private readonly RegistryService $registryService,
        private readonly OpportunityService $opportunityService,
        private readonly RequestManagementService $panel,
    ) {}

    /**
     * @return array{opportunity: Opportunity}
     */
    public function create(User $actor, CreateRequestData $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            // Step 1: resolve the client Registry — an existing one, or a
            // brand-new Registry+PersonalData from the submitted identity
            // block (D-2's XOR is already enforced by StoreRequestRequest).
            $registry = $data->registryId !== null
                ? Registry::findOrFail($data->registryId)
                : $this->registryService->create($actor, $this->newClientRegistryData(), $data->clientProfile);

            // Step 2: the Opportunity itself, through the shared service. The
            // initial attribution (source/reporter/Sede operativa) and reward
            // assignments travel with it; every other relation stays unset
            // (out of scope, D-4). `reporterId` is part of the insert, so RewardAssignmentWriter
            // (invoked by OpportunityService::create) already targets the right
            // beneficiary — no retarget step needed.
            //
            // Operatore and Sede operativa DEFAULT to the creating actor and
            // the actor's own Sede (user directive 2026-08-04): a request is
            // always worked by whoever opened it, from the Sede they belong to,
            // and that holds whether or not the form showed the two fields —
            // an actor without `request-management.assignOperator` /
            // `operational-sites.viewAny` never renders them, so an absent key
            // is exactly the case this default covers. A submitted value (only
            // an actor holding those abilities gets past the controller's
            // guards) always wins.
            $opportunity = $this->opportunityService->create(new CreateOpportunityData(
                registryId: $registry->id,
                referentId: null,
                commercialId: null,
                reporterId: $data->reporterId,
                supervisorId: null,
                sourceId: $data->sourceId,
                leadId: null,
                managerSlots: $this->operatorManagerSlots($data->operatorId ?? $actor->id),
                operationalSiteId: $data->operationalSiteId ?? $this->actorOperationalSiteId($actor),
                productLines: $data->productLines,
                // "Prodotti di interesse" (user directive 2026-07-31): already
                // checked against the product lines above by
                // StoreRequestRequest, so the shared writer's cross-category
                // branch (which would add a line) is unreachable from here.
                productsOfInterest: $data->productsOfInterest,
                startDate: null,
                estimatedValue: null,
                expectedCloseDate: null,
                successProbability: null,
                rewards: $data->rewards,
                generalNotes: $data->generalNotes,
            ), $actor);

            // Step 3: the planned callback, submitted at creation too (user
            // directive 2026-07-31) — NOT mass-assignable (D-4 guard), so
            // written AFTER the insert like every other operative field of
            // this panel.
            $this->applyOperativeFields($opportunity, $data);

            return $this->panel->loadWorkPanel($opportunity);
        });
    }

    /**
     * The planned callback, optional (user directive 2026-07-31). Nothing
     * submitted means nothing to save: the early return keeps a plain create
     * at the single insert it has always been.
     */
    private function applyOperativeFields(Opportunity $opportunity, CreateRequestData $data): void
    {
        if ($data->nextCallbackAt !== null) {
            $opportunity->next_callback_at = $data->nextCallbackAt;
            $opportunity->save();
        }
    }

    /**
     * The GA2 "Operatore" as ordered manager slots (user directive
     * 2026-07-29): OpportunityService maps slot index+1 to the pivot
     * `position`, so the operator sits at index 1 with GA1 left empty —
     * Opportunity::OPERATOR_MANAGER_POSITION expressed in the slots
     * vocabulary. Always called with an operator: the actor is the default
     * (see create()), so a request never lands without one.
     *
     * @return array<int, int|null>
     */
    private function operatorManagerSlots(int $operatorId): array
    {
        $slots = array_fill(0, Opportunity::OPERATOR_MANAGER_POSITION, null);
        $slots[Opportunity::OPERATOR_MANAGER_POSITION - 1] = $operatorId;

        return $slots;
    }

    /**
     * The creating actor's own Sede operativa, from their employment profile
     * (spec 0015) — `null` when they have no profile or no Sede on it, which
     * leaves the request without one exactly as before.
     *
     * `loadMissing`: the authenticated actor arrives with no relations loaded,
     * and Model::preventLazyLoading() is active outside production
     * (backend.md §3).
     */
    private function actorOperationalSiteId(User $actor): ?int
    {
        return $actor->loadMissing('employment')->employment?->operational_site_id;
    }

    /**
     * A minimal new Registry: no source/sectors/referents/managers, not a
     * supplier — the create-request form collects only anagrafica + product
     * lines (D-4), nothing else to derive these from.
     */
    private function newClientRegistryData(): CreateRegistryData
    {
        return new CreateRegistryData(
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
        );
    }
}
