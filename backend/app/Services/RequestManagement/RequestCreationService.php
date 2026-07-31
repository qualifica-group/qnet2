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
        private readonly RequestAttributeValueWriter $attributeValueWriter,
        private readonly RequestWorkflowStatusWriter $workflowStatusWriter,
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
            // initial attribution (source/reporter/Sede operativa) and reward
            // assignments travel with it; every other relation stays unset
            // (out of scope, D-4). `reporterId` is part of the insert, so RewardAssignmentWriter
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
                operationalSiteId: $data->operationalSiteId,
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
            ));

            // Step 3: the operative fields the work panel edits, submitted at
            // creation too (user directive 2026-07-31). They run AFTER the
            // insert on purpose: none of the three is mass-assignable (D-4
            // guard), and both the working status and the dynamic values are
            // validated against sets that only exist once the product lines
            // are persisted.
            $this->applyOperativeFields($opportunity, $actor, $data);

            // Spec 0062, D3: the distinct "new request" form, never the full
            // edit work panel's own layout.
            return $this->panel->loadWorkPanel($opportunity, FormMode::Create);
        });
    }

    /**
     * The working status, the planned callback and the dynamic values, all
     * optional (user directive 2026-07-31). Each goes through the SAME writer
     * the panel's PATCH uses, so the two channels can never diverge on the
     * rules attached to them — most notably the note a `requires_note` status
     * demands (spec 0054 D-5), enforced here exactly as on an advance.
     *
     * Nothing submitted means nothing to save: the early return keeps a plain
     * create at the single insert it has always been.
     */
    private function applyOperativeFields(Opportunity $opportunity, User $actor, CreateRequestData $data): void
    {
        // Discarded: on create there is no previous state to diff against, and
        // the record's own `created` activity entry is the audit trail (the
        // panel's explicit entry exists only because a PATCH of these
        // non-fillable columns would otherwise leave no trace at all).
        $changed = [];
        $old = [];

        if ($data->workflowStatusId !== null) {
            $this->workflowStatusWriter->apply($opportunity, $data->workflowStatusId, $actor, $data->statusNote, $changed, $old);
            // OpportunityService::create left the resolver's own status loaded
            // on the relation; the panel below reads it through loadMissing(),
            // which would keep serving that stale row.
            $opportunity->unsetRelation('workflowStatus');
        }

        if ($data->nextCallbackAt !== null) {
            $opportunity->next_callback_at = $data->nextCallbackAt;
        }

        if ($data->attributeValues !== null) {
            $this->attributeValueWriter->apply($opportunity, $data->attributeValues, $changed, $old);
        }

        if ($opportunity->isDirty()) {
            $opportunity->save();
        }
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
