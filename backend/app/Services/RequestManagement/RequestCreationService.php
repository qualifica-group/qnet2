<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Registries\CreateRegistryData;
use App\DataObjects\RequestManagement\CreateRequestData;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\RequestManagement\RequestAttributeResolver;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Services\OpportunityService;
use App\Services\Quotes\QuoteAttributeValueWriter;
use App\Services\Quotes\QuoteRootScope;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Services\QuoteService;
use App\Services\RegistryService;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
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
 * onto the freshly created Quote instead (Step 5) — never through
 * OpportunityService's own reward channel, which would target the wrong
 * owner.
 */
final class RequestCreationService
{
    /** Spec 0411, D-2: an offer row whose product sits under none of the request's roots. */
    public const string OFFER_LINE_OUTSIDE_ROOTS_MESSAGE = 'The product ":product" does not belong to any parent product category of the request: add its product category to the product lines, or remove the row.';

    public function __construct(
        private readonly RegistryService $registryService,
        private readonly OpportunityService $opportunityService,
        private readonly QuoteService $quoteService,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly RequestManagementService $panel,
        private readonly RequestAttributeResolver $attributeResolver,
        private readonly QuoteAttributeValueWriter $attributeValueWriter,
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly SimplifiedOfferLineNormalizer $simplifiedOfferLineNormalizer,
        private readonly QuoteRootScope $rootScope,
        private readonly QuoteWorkflowResolver $workflowResolver,
    ) {}

    /**
     * @return array{quote: Quote, created_quote_ids: array<int, int>}
     */
    public function create(User $actor, CreateRequestData $data): array
    {
        return DB::transaction(function () use ($actor, $data): array {
            // Step 1: resolve the client Registry — an existing one, or a
            // brand-new Registry+PersonalData from the submitted identity
            // block (D-2's XOR is already enforced by StoreRequestRequest).
            $registry = $data->registryId !== null
                ? Registry::findOrFail($data->registryId)
                : $this->registryService->create($actor, $this->newClientRegistryData($data), $data->clientProfile);

            // Operatore and Sede operativa DEFAULT to the creating actor and
            // the actor's own Sede (user directive 2026-08-04): a request is
            // always worked by whoever opened it, from the Sede they belong
            // to, and that holds whether or not the form showed the two
            // fields — an actor without `request-management.assignOperator`/
            // `operational-sites.viewAny` never renders them, so an absent
            // key is exactly the case this default covers. A submitted value
            // (only an actor holding those abilities gets past the
            // controller's guards) always wins. Spec 0097: the operator half
            // now arrives as one SLOT of the submitted team, so the default
            // fills that slot alone and leaves the rest of the team as sent.
            //
            // Spec 0087, D-13: the operator lands SOLELY on the Offerta's
            // own GA2 slot (Step 3 below) — the Opportunity no longer
            // receives it from here. Appartenenza (D-6) is still guaranteed:
            // QuoteManagerWriter's own promotion path (reused here through
            // CreateQuoteData's `promoteManagersToOpportunity`) appends the
            // operator to the Opportunity's first FREE manager slot, never
            // overwriting an already-occupied one.
            $managerSlots = $this->managerSlotsWithOperator($data->managerSlots, $actor);
            $operationalSiteId = $data->operationalSiteId ?? $this->actorOperationalSiteId($actor);

            // Step 2: the Opportunity, through the shared service. The
            // initial attribution (reporter/commercial/Sede operativa) travels
            // with it; every other relation stays unset (out of scope, D-4).
            // `rewards` is withheld (see class docblock) — Step 5 syncs them
            // onto the Offerta instead. `managerSlots` is null (spec 0087,
            // D-13): the Opportunity is born with no Gestori Account of its
            // own from this channel — the operator is promoted onto it (if
            // needed) only when the Offerta's own GA2 is written, Step 3.
            $opportunity = $this->opportunityService->create(new CreateOpportunityData(
                registryId: $registry->id,
                referentId: null,
                commercialId: $data->commercialId,
                commercialPartnerId: $data->commercialPartnerId,
                reporterId: $data->reporterId,
                supervisorId: null,
                leadId: null,
                managerSlots: null,
                operationalSiteId: $operationalSiteId,
                productLines: $data->productLines,
                // "Prodotti di interesse" is NOT written from this module any
                // more (user directive 2026-09-10): the create form's section
                // is gone, so the Opportunity is born with an empty
                // collection and the picker stays an Opportunities-form
                // concern. The named argument is simply left out — the DTO
                // defaults it to null, i.e. "untouched".
                startDate: null,
                // "Previsione commerciale" (spec 0313, D-4): written at
                // creation too, `null` when the form left them blank.
                estimatedValue: $data->estimatedValue,
                expectedCloseDate: $data->expectedCloseDate,
                successProbability: $data->successProbability,
                rewards: null,
                generalNotes: $data->generalNotes,
            ), $actor);

            // Step 3: the Offerte (spec 0086, D-5), always through
            // QuoteService::create() — see createRootQuote().
            $this->assertOfferLinesFitManagementMode($opportunity, $data);

            // Spec 0411, D-2: ONE Offerta per root category ("categoria
            // madre") of the product lines, in order of first appearance,
            // each bound to its root and carrying only that root's offer
            // rows. Step 4/5: callback on each, rewards on the first alone.
            $roots = array_keys($this->rootScope->groupByRoot(array_map(
                static fn (array $line): int => (int) $line['product_category_id'],
                $data->productLines,
            ))) ?: [null];
            $offerLinesByRoot = $this->offerLinesByRoot($data->offerLines, $roots);
            $quotes = [];

            foreach ($roots as $index => $rootId) {
                $quote = $this->createRootQuote($actor, $data, $opportunity, $rootId, $rootId === null ? $data->offerLines : ($offerLinesByRoot[$rootId] ?? null), $managerSlots, $operationalSiteId, $index === 0);
                $this->applyOperativeFields($quote, $data);

                if ($index === 0 && $data->rewards !== null) {
                    $this->rewardAssignmentWriter->sync($quote, $data->rewards);
                }

                $quotes[] = $quote;
            }

            // Step 6: "Informazioni aggiuntive" (user directive 2026-08-07) —
            // each Offerta takes the codes its own root resolves (D-2). It
            // runs LAST because that set does not exist before the insert.
            $this->applyAttributeValues($quotes, $data);

            return [
                ...$this->panel->loadWorkPanel($quotes[0]),
                'created_quote_ids' => array_map(static fn (Quote $created): int => $created->id, $quotes),
            ];
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
     * Spec 0411, D-2: one Offerta bound to $rootId, through the SAME
     * QuoteService::create() the quotes module uses — the ONE entry point that
     * generates `code`, resolves the working status and recalculates every
     * aggregate. Attribution, team and Sede are the values just written on the
     * Opportunity; the operator lands on the Offerta's GA2 slot and is promoted
     * onto the Opportunity's first free slot when missing (spec 0087, D-6/D-13).
     * The requested working status goes to the first Offerta, and to the others
     * only where their own workflow offers it (otherwise their default stays).
     *
     * @param  array<int, QuoteLineData>|null  $offerLines  this root's rows, null when the form sent none
     * @param  array<int, int|null>  $managerSlots
     */
    private function createRootQuote(User $actor, CreateRequestData $data, Opportunity $opportunity, ?int $rootId, ?array $offerLines, array $managerSlots, ?int $operationalSiteId, bool $first): Quote
    {
        // Spec 0114, D-4/D-5: a `simplified_offer_line` root freezes quantity/
        // unit price/VAT on every submitted row — every row is new here.
        $offerLines = $offerLines === null
            ? null
            : $this->simplifiedOfferLineNormalizer->normalizeForCreation($opportunity, $offerLines, $rootId);

        return $this->quoteService->create(new CreateQuoteData(
            code: null,
            // Spec 0171 rev.2: born with the automatic `<code> - <products>` title.
            title: null,
            opportunityId: $opportunity->id,
            workflowStatusId: $first || $this->statusFitsRoot($data->workflowStatusId, $opportunity, $rootId) ? $data->workflowStatusId : null,
            note: null,
            commercialId: $data->commercialId,
            commercialIdSubmitted: true,
            commercialPartnerId: $data->commercialPartnerId,
            commercialPartnerIdSubmitted: true,
            reporterId: $data->reporterId,
            reporterIdSubmitted: true,
            supervisorId: $data->supervisorId,
            supervisorIdSubmitted: $data->supervisorIdSubmitted,
            internalNotes: null,
            operationalSiteId: $operationalSiteId,
            operationalSiteIdSubmitted: true,
            offerLines: $offerLines,
            managerSlots: $managerSlots,
            promoteManagersToOpportunity: true,
            sourceId: $data->sourceId,
            rootProductCategoryId: $rootId,
        ), $actor);
    }

    /**
     * Whether the working status picked in the form belongs to the workflow an
     * Offerta bound to $rootId resolves to — probed on a transient Offerta
     * (the same technique ValidatesQuoteWorkflowStatus uses), never written.
     */
    private function statusFitsRoot(?int $statusId, Opportunity $opportunity, ?int $rootId): bool
    {
        if ($statusId === null) {
            return false;
        }

        $probe = new Quote;
        $probe->root_product_category_id = $rootId;
        $probe->setRelation('opportunity', $opportunity->load('productLines'));
        $probe->setRelation('offerLines', new EloquentCollection);

        return $this->workflowResolver->statusesFor($this->workflowResolver->resolve($probe))
            ->pluck('id')
            ->contains($statusId);
    }

    /**
     * The submitted offer rows keyed by the root of their product (D-2). A
     * product outside every root of the product lines is refused here: no
     * Offerta could carry it, and it must not open a root of its own.
     *
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, int|null>  $roots
     * @return array<int, array<int, QuoteLineData>>
     *
     * @throws ValidationException
     */
    private function offerLinesByRoot(?array $offerLines, array $roots): array
    {
        if ($offerLines === null) {
            return [];
        }

        $grouped = array_fill_keys(array_filter($roots, static fn (?int $root): bool => $root !== null), []);

        if ($grouped === []) {
            return [];
        }

        $products = Product::query()
            ->whereIn('id', array_map(static fn (QuoteLineData $line): int => $line->productId, $offerLines))
            ->get(['id', 'name', 'category_id'])
            ->keyBy('id');

        foreach ($offerLines as $index => $line) {
            $product = $products->get($line->productId);
            $rootId = $product?->category_id === null ? null : $this->rootScope->rootIdOf((int) $product->category_id);

            if ($rootId === null || ! array_key_exists($rootId, $grouped)) {
                throw ValidationException::withMessages([
                    "offer_lines.{$index}.product_id" => [__(self::OFFER_LINE_OUTSIDE_ROOTS_MESSAGE, ['product' => $product->name ?? (string) $line->productId])],
                ]);
            }

            $grouped[$rootId][] = $line;
        }

        return $grouped;
    }

    /**
     * The dynamic "Informazioni aggiuntive" of the created Offerte (user
     * directive 2026-08-07), through the SAME writer the Offerte module uses.
     * Spec 0411, D-2: each Offerta takes the submitted codes its own root
     * resolves; a code no Offerta resolves is still a 422 (on the first one,
     * which validates the leftovers too).
     *
     * The `$changed`/`$old` pair is discarded on purpose: a creation has no
     * before-state to log, the record's own creation entry carries the row.
     *
     * @param  array<int, Quote>  $quotes
     *
     * @throws ValidationException a submitted code is not applicable to any created Offerta
     */
    private function applyAttributeValues(array $quotes, CreateRequestData $data): void
    {
        if ($data->attributeValues === null) {
            return;
        }

        $applicableByQuote = array_map(fn (Quote $quote): Collection => $this->attributeResolver->resolve($quote), $quotes);

        foreach ($quotes as $index => $quote) {
            $codes = $applicableByQuote[$index]->pluck('code')->all();
            $submitted = array_intersect_key($data->attributeValues, array_flip($codes));

            if ($index === 0) {
                // Leftovers ride on the first Offerta so the writer reports them.
                $otherCodes = array_merge([], ...array_map(
                    static fn (Collection $applicable): array => $applicable->pluck('code')->all(),
                    array_slice($applicableByQuote, 1),
                ));
                $submitted += array_diff_key($data->attributeValues, array_flip([...$codes, ...$otherCodes]));
            }

            if ($submitted === [] && $index > 0) {
                continue;
            }

            $changed = [];
            $old = [];

            $this->attributeValueWriter->apply(
                $quote,
                $submitted,
                $changed,
                $old,
                $applicableByQuote[$index],
                $this->attributeResolver->layout($quote, FormMode::Create),
            );

            $quote->save();
        }
    }

    /**
     * The planned callback, optional (user directive 2026-07-31). Nothing
     * submitted means nothing to save: the early return keeps a plain create
     * at the single insert it has always been.
     */
    private function applyOperativeFields(Quote $quote, CreateRequestData $data): void
    {
        if ($data->nextCallbackAt !== null) {
            $quote->next_callback_at = $data->nextCallbackAt;
            $quote->save();
        }
    }

    /**
     * The submitted team (spec 0097, D-1) with the OPERATOR slot guaranteed
     * filled: a request is always worked by somebody, and by default by
     * whoever opened it (user directive 2026-08-04) — so an absent payload,
     * or one leaving `ManagerPositions::OPERATOR` empty, receives the actor
     * THERE (AC-002, the exact behaviour the former `operatorId ?? $actor->id`
     * default had). Every other slot travels through untouched.
     *
     * The actor is first dropped from any OTHER slot they were submitted in:
     * a manager occupies exactly one slot (ValidatesManagerSlots' own
     * invariant, which the default must not break), and the module's rule is
     * that the creator is the operator — same "moved, never duplicated"
     * treatment RequestOperatorWriter::slotsWithOperator() gives the panel.
     *
     * The result feeds QuoteManagerWriter through CreateQuoteData, which is
     * what projects `quotes.operator_id` from the OPERATOR slot (spec 0087,
     * D-13) and promotes anyone missing onto the Opportunity's first free
     * slot.
     *
     * @param  array<int, int|null>|null  $slots
     * @return array<int, int|null>
     */
    private function managerSlotsWithOperator(?array $slots, User $actor): array
    {
        $slots = array_pad(array_values($slots ?? []), ManagerPositions::OPERATOR, null);
        $operatorIndex = ManagerPositions::OPERATOR - 1;

        if ($slots[$operatorIndex] !== null) {
            return $slots;
        }

        foreach ($slots as $index => $userId) {
            if ($userId === $actor->id) {
                $slots[$index] = null;
            }
        }

        $slots[$operatorIndex] = $actor->id;

        return $slots;
    }

    /**
     * The creating actor's own PHYSICAL Sede operativa (spec 0103, D-3: at
     * most one), from their employment profile — `null` when they have no
     * profile or no physical Sede on it, which leaves the request without
     * one exactly as before (AC-032).
     *
     * `loadMissing('employment.operationalSites')`: the authenticated actor
     * arrives with no relations loaded, and Model::preventLazyLoading() is
     * active outside production (backend.md §3) — primaryOperationalSiteId()
     * itself reads off the already-loaded `operationalSites` collection, so
     * the nested eager-load is what keeps it from lazy-loading.
     */
    private function actorOperationalSiteId(User $actor): ?int
    {
        return $actor->loadMissing('employment.operationalSites')->employment?->primary_operational_site_id;
    }

    /**
     * A minimal new Registry carrying the request's own attribution (ticket
     * #427523): Fonte, Commerciale and Segnalatore are the client's too when
     * the request is what brings the client in. Nothing else is derived —
     * the create-request form collects no sectors/referents/managers (D-4).
     */
    private function newClientRegistryData(CreateRequestData $data): CreateRegistryData
    {
        return new CreateRegistryData(
            sourceId: $data->sourceId,
            sectorIds: null,
            referentIds: null,
            managerSlots: null,
            supervisorId: null,
            commercialId: $data->commercialId,
            commercialPartnerId: $data->commercialPartnerId,
            reporterId: $data->reporterId,
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
