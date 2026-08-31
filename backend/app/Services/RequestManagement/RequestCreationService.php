<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Enums\CategoryManagementMode;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\RequestManagement\RequestAttributeResolver;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Services\OpportunityService;
use App\Services\Quotes\QuoteAttributeValueWriter;
use App\Services\QuoteService;
use App\Services\RegistryService;
use App\Support\ManagerPositions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creation entry point for the request-management work panel (spec 0057,
 * POST /api/request-management; spec 0086, D-5: creates the Opportunity AND
 * the Offerta, in ONE transaction, AC-027/AC-030). A dedicated class rather
 * than growing RequestManagementService (which owns the panel's read/update
 * lifecycle, a distinct concern — SRP, file-size split per engineering.md §6).
 *
 * The client anagraphic block (D-2) either points at an existing Registry
 * (`registry_id`, left untouched) or creates a brand-new one through
 * RegistryService::create() — this class never MODIFIES an existing
 * Registry's anagrafica, that stays the work panel's own competence. The
 * Opportunity itself is created through the SAME OpportunityService the
 * opportunities form uses, so the `OPP_{id}` name derivation (spec 0057,
 * D-5) and the product-lines sync are never duplicated here. The Offerta is
 * then created through the SAME QuoteService::create() the quotes module
 * uses (constraints: "consuma QuoteService::create(), non lo si modifica"),
 * born with no product lines (AC-028).
 *
 * D-4: the reward beneficiary is now the Offerta's OWN Segnalatore, so
 * `rewards` is deliberately withheld from CreateOpportunityData and synced
 * onto the freshly created Quote instead (Step 3) — never through
 * OpportunityService's own reward channel, which would target the wrong
 * owner.
 */
final class RequestCreationService
{
    public function __construct(
        private readonly RegistryService $registryService,
        private readonly OpportunityService $opportunityService,
        private readonly QuoteService $quoteService,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly RequestManagementService $panel,
        private readonly RequestAttributeResolver $attributeResolver,
        private readonly QuoteAttributeValueWriter $attributeValueWriter,
        private readonly OpportunityProductLineCoverage $coverage,
    ) {}

    /**
     * @return array{quote: Quote}
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

            // Operatore and Sede operativa DEFAULT to the creating actor and
            // the actor's own Sede (user directive 2026-08-04): a request is
            // always worked by whoever opened it, from the Sede they belong
            // to, and that holds whether or not the form showed the two
            // fields — an actor without `request-management.assignOperator`/
            // `operational-sites.viewAny` never renders them, so an absent
            // key is exactly the case this default covers. A submitted value
            // (only an actor holding those abilities gets past the
            // controller's guards) always wins.
            //
            // Spec 0087, D-13: the operator lands SOLELY on the Offerta's
            // own GA2 slot (Step 4 below) — the Opportunity no longer
            // receives it from here. Appartenenza (D-6) is still guaranteed:
            // QuoteManagerWriter's own promotion path (reused here through
            // CreateQuoteData's `promoteManagersToOpportunity`) appends the
            // operator to the Opportunity's first FREE manager slot, never
            // overwriting an already-occupied one.
            $operatorId = $data->operatorId ?? $actor->id;
            $operationalSiteId = $data->operationalSiteId ?? $this->actorOperationalSiteId($actor);

            // Step 2: the Opportunity, through the shared service. The
            // initial attribution (source/reporter/Sede operativa) travels
            // with it; every other relation stays unset (out of scope, D-4).
            // `rewards` is withheld (see class docblock) — Step 4 syncs them
            // onto the Offerta instead. `managerSlots` is null (spec 0087,
            // D-13): the Opportunity is born with no Gestori Account of its
            // own from this channel — the operator is promoted onto it (if
            // needed) only when the Offerta's own GA2 is written, Step 4.
            $opportunity = $this->opportunityService->create(new CreateOpportunityData(
                registryId: $registry->id,
                referentId: null,
                commercialId: null,
                reporterId: $data->reporterId,
                supervisorId: null,
                sourceId: $data->sourceId,
                leadId: null,
                managerSlots: null,
                operationalSiteId: $operationalSiteId,
                productLines: $data->productLines,
                // "Prodotti di interesse" (user directive 2026-07-31): already
                // checked against the product lines above by
                // StoreRequestRequest, so the shared writer's cross-category
                // branch (which would add a line) is unreachable from here.
                // Spec 0086: this stays an Opportunity-level collection —
                // POST is the one endpoint of this module that still accepts
                // it (data_contract).
                productsOfInterest: $data->productsOfInterest,
                startDate: null,
                estimatedValue: null,
                expectedCloseDate: null,
                successProbability: null,
                rewards: null,
                generalNotes: $data->generalNotes,
            ), $actor);

            // Step 3: the planned callback, submitted at creation too (user
            // directive 2026-07-31) — NOT mass-assignable (D-4 guard), so
            // written AFTER the insert like every other operative field of
            // this panel.
            $this->applyOperativeFields($opportunity, $data);

            // Step 4: the Offerta itself (spec 0086, D-5), always through
            // QuoteService::create() — the ONE entry point that generates
            // `code`, bootstraps `quote_workflow_status_id` and recalculates
            // every aggregate. Its REVENUE rows travel with it when the form
            // filled any in (user directive 2026-08-07; AC-028's empty offer
            // stays the default), so coverage/aggregates/derived name are the
            // service's own concern here too, never duplicated.
            // `reporter_id`/`operational_site_id` are the SAME resolved
            // values just used for the Opportunity, so the two records never
            // disagree at creation time. `supervisor_id` is deliberately NOT
            // one of them any more (spec 0087, D-13): it follows the general
            // inheritance rule (applySnapshotDefaults(), like commercial and
            // reporter), never a value this channel picks. The operator
            // lands on the Offerta's own GA2 slot instead, via
            // `managerSlots`, promoted onto the Opportunity's first free
            // slot when it is not already one of its Gestori Account (D-6).
            $this->assertOfferLinesFitManagementMode($opportunity, $data);

            $quote = $this->quoteService->create(new CreateQuoteData(
                code: null,
                title: $opportunity->name,
                opportunityId: $opportunity->id,
                workflowStatusId: null,
                note: null,
                commercialId: null,
                commercialIdSubmitted: false,
                reporterId: $data->reporterId,
                reporterIdSubmitted: true,
                supervisorId: null,
                supervisorIdSubmitted: false,
                internalNotes: null,
                operationalSiteId: $operationalSiteId,
                operationalSiteIdSubmitted: true,
                offerLines: $data->offerLines,
                managerSlots: $this->operatorManagerSlots($operatorId),
                promoteManagersToOpportunity: true,
            ), $actor);

            // Step 5: reward assignments (D-4/D-12, AC-023) — the Offerta's
            // own Segnalatore is the beneficiary, so the sync runs only once
            // the Offerta exists.
            if ($data->rewards !== null) {
                $this->rewardAssignmentWriter->sync($quote, $data->rewards);
            }

            // Step 6: "Informazioni aggiuntive" (user directive 2026-08-07) —
            // written on the Offerta, validated against the applicable set the
            // just-inserted product lines resolve (RequestAttributeResolver,
            // D-1). It runs LAST because that set does not exist before the
            // insert: a code the categories do not carry is a 422 keyed
            // `attribute_values.<code>`, rolling the whole creation back.
            $this->applyAttributeValues($quote, $data);

            return $this->panel->loadWorkPanel($quote);
        });
    }

    /**
     * Spec 0077 / user directive 2026-08-07: an opportunity managed on a
     * `single` product category carries ONE offer row. ValidatesQuoteLines'
     * own enforceSingleOfferLine() cannot fire on this channel — it resolves
     * the opportunity from a submitted `opportunity_id`, and here the
     * Opportunity is born in this very transaction — so the identical rule is
     * checked here, against the just-inserted product lines, with the SAME
     * shared message the quotes endpoints report.
     *
     * @throws ValidationException more than one offer row on a `single`-managed classification
     */
    private function assertOfferLinesFitManagementMode(Opportunity $opportunity, CreateRequestData $data): void
    {
        if ($data->offerLines === null || count($data->offerLines) < 2) {
            return;
        }

        if ($this->coverage->managementModeOf($opportunity) !== CategoryManagementMode::Single) {
            return;
        }

        throw ValidationException::withMessages([
            'offer_lines' => [__(OpportunityProductLineCoverage::SINGLE_OFFER_LINE_MESSAGE)],
        ]);
    }

    /**
     * The dynamic "Informazioni aggiuntive" of the freshly created Offerta
     * (user directive 2026-08-07), through the SAME writer the Offerte module
     * uses — fed THIS module's applicable set (D-1: the Opportunity's product
     * lines, since a request's Offerta is born with no offer lines).
     *
     * The `$changed`/`$old` pair the writer reports into is discarded here on
     * purpose: a creation has no before-state to log, and the record's own
     * creation entry already carries the row.
     *
     * @throws ValidationException a submitted code is not applicable to the created classification
     */
    private function applyAttributeValues(Quote $quote, CreateRequestData $data): void
    {
        if ($data->attributeValues === null) {
            return;
        }

        $changed = [];
        $old = [];

        $this->attributeValueWriter->apply(
            $quote,
            $data->attributeValues,
            $changed,
            $old,
            $this->attributeResolver->resolve($quote),
        );

        $quote->save();
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
     * 2026-07-29; spec 0087, D-13: now feeds the Offerta's OWN
     * `manager_slots`, via `QuoteManagerWriter`, no longer the Opportunity's)
     * — `ManagerPositions::OPERATOR` expressed in the slots vocabulary
     * (index+1 = position), so the operator sits at the OPERATOR index with
     * every slot before it left empty. Always called with an operator: the
     * actor is the default (see create()), so a request never lands without
     * one.
     *
     * @return array<int, int|null>
     */
    private function operatorManagerSlots(int $operatorId): array
    {
        $slots = array_fill(0, ManagerPositions::OPERATOR, null);
        $slots[ManagerPositions::OPERATOR - 1] = $operatorId;

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
