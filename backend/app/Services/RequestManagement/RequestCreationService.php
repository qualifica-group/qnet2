<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\Registry;
use App\Models\User;
use App\RequestManagement\ApplicableAttribute;
use App\Services\OpportunityService;
use App\Services\RegistryService;
use Illuminate\Support\Collection;
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
 * D-5), the status fallback (SystemStatusGuard) and the product-lines sync
 * are never duplicated here.
 */
final class RequestCreationService
{
    public function __construct(
        private readonly RegistryService $registryService,
        private readonly OpportunityService $opportunityService,
        private readonly RequestManagementService $panel,
    ) {}

    /**
     * @return array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>, attribute_layout: array<string, mixed>|null}
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
            // initial attribution (source/reporter) and reward assignments
            // travel with it; every other relation stays unset (out of scope,
            // D-4). `reporterId` is part of the insert, so RewardAssignmentWriter
            // (invoked by OpportunityService::create) already targets the right
            // beneficiary — no retarget step needed.
            $opportunity = $this->opportunityService->create(new CreateOpportunityData(
                registryId: $registry->id,
                referentId: null,
                commercialId: null,
                reporterId: $data->reporterId,
                supervisorId: null,
                sourceId: $data->sourceId,
                leadId: null,
                opportunityStatusId: null,
                managerSlots: $this->operatorManagerSlots($data->operatorId),
                productLines: $data->productLines,
                startDate: null,
                estimatedValue: null,
                expectedCloseDate: null,
                successProbability: null,
                rewards: $data->rewards,
            ));

            // Spec 0062, D3: the distinct "new request" form, never the full
            // edit work panel's own layout.
            return $this->panel->loadWorkPanel($opportunity, FormMode::Create);
        });
    }

    /**
     * The GA2 "Operatore" as ordered manager slots (user directive
     * 2026-07-29): OpportunityService maps slot index+1 to the pivot
     * `position`, so the operator sits at index 1 with GA1 left empty —
     * Opportunity::OPERATOR_MANAGER_POSITION expressed in the slots
     * vocabulary. `null` when no operator was submitted: nothing to sync.
     *
     * @return array<int, int|null>|null
     */
    private function operatorManagerSlots(?int $operatorId): ?array
    {
        if ($operatorId === null) {
            return null;
        }

        $slots = array_fill(0, Opportunity::OPERATOR_MANAGER_POSITION, null);
        $slots[Opportunity::OPERATOR_MANAGER_POSITION - 1] = $operatorId;

        return $slots;
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
