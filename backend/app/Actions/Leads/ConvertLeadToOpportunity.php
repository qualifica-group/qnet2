<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Quotes\CreateQuoteData;
use App\Enums\CategoryManagementMode;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use App\Services\OpportunityService;
use App\Services\Quotes\ProductOfferLineResolver;
use App\Services\QuoteService;
use Illuminate\Validation\ValidationException;

/**
 * Contextual Lead -> Opportunity conversion (spec 0044): given a just-created
 * Lead, derives and persists its linked Opportunity. Called by
 * LeadService::create() from WITHIN the same DB::transaction it already
 * opened around the Lead insert, so a failure here (e.g. AC-012, empty
 * product lines) rolls the Lead back too — this class does not open its own
 * outer transaction.
 *
 * Reuses LeadOpportunityDefaultsResolver (the single BR-1 derivation point,
 * spec 0040) for registry_id/source_id/product lines, and
 * OpportunityService::create() for the actual persistence (product lines
 * sync, and — spec 0057, D-5 — the `OPP_{id}` name derivation), rather than
 * re-implementing either.
 *
 * Spec 0094, D-3/AC-062, amended by spec 0102 D-1/AC-020: the conversion
 * ALWAYS creates the ONE collegata Offerta, through the SAME
 * QuoteService::create() the quotes module uses — never a second
 * implementation of its code/status/aggregates — with one REVENUE line per
 * product of interest, or zero lines when the Lead carries none (spec 0102
 * D-1 supersedes spec 0094 AC-065, which used to skip the Offerta entirely
 * on zero products). D-6/AC-067: a derived classification resolving to a
 * `single` management-mode root accepts one offer row only; that check runs
 * AFTER the Opportunity (and its product lines) are persisted — mirrors
 * RequestCreationService::assertOfferLinesFitManagementMode(), the same
 * shared OpportunityProductLineCoverage the FormRequest-based
 * ValidatesQuoteLines::enforceSingleOfferLine() cannot reach here, since the
 * Opportunity is born in this very (possibly not self-opened) transaction —
 * a rejection at that point still rolls back everything Step 3 wrote.
 */
final class ConvertLeadToOpportunity
{
    public function __construct(
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly OpportunityService $opportunityService,
        private readonly OpportunityProductLineCoverage $coverage,
        private readonly QuoteService $quoteService,
        private readonly ProductOfferLineResolver $offerLineResolver,
    ) {}

    /**
     * @param  ?User  $actor  who triggered the conversion, propagated to
     *                        OpportunityService so the assignment notifications
     *                        (spec 0081) can exclude them and name them as the
     *                        author. Also who the generated Offerta (D-3,
     *                        always created — spec 0102 D-1) is attributed
     *                        to — QuoteService::create() requires a non-null
     *                        actor, so a null one here (today: no caller
     *                        passes one) skips the Offerta entirely rather
     *                        than crashing; the Opportunity is still created
     *                        and still carries the transferred products of
     *                        interest (AC-061).
     */
    public function handle(Lead $lead, ?User $actor = null): Opportunity
    {
        // Step 1: derive the BR-1 values (registry_id/source_id), the
        // campaign/project's N product lines (spec 0094, AC-060), and the
        // lead's own products of interest (AC-061) — REQUIRED_RELATIONS
        // already eager-loaded productsOfInterest, so this never lazy-loads.
        $defaults = $this->defaultsResolver->resolve($lead);
        $productIds = $lead->productsOfInterest->pluck('id')->map(intval(...))->unique()->values()->all();

        // Step 2: a campaign/project with no product line derives nothing to
        // seed the opportunity's mandatory classification with (AC-012) —
        // reject before persisting anything.
        if ($defaults->productLines === []) {
            throw ValidationException::withMessages([
                'product_lines' => ["The lead's campaign has no business function or product category to derive an opportunity from."],
            ]);
        }

        // Step 3: persist through OpportunityService::create(), so product
        // line sync, the products-of-interest transfer (AC-061, covered by
        // construction: they came from categories the SAME campaign lines
        // cover) and the `OPP_{id}` name derivation (spec 0057 D-5) stay the
        // single implementation.
        $opportunity = $this->opportunityService->create(new CreateOpportunityData(
            registryId: $defaults->values['registry_id'],
            referentId: null,
            commercialId: null,
            reporterId: null,
            // User directive 2026-07-21: the lead's Operator no longer becomes
            // the Supervisor — it seeds a "Gestore Account" slot below, and the
            // Supervisor is left empty.
            supervisorId: null,
            sourceId: $defaults->values['source_id'],
            leadId: $lead->id,
            // User directive 2026-07-22: the Operator becomes G.A. 2; G.A. 1 is
            // still materialized, but empty (a null leading slot, gap-aware).
            managerSlots: $lead->operator_id === null ? null : [null, $lead->operator_id],
            productLines: $this->toProductLineAttributes($defaults->productLines),
            startDate: null,
            estimatedValue: null,
            expectedCloseDate: null,
            successProbability: null,
            productsOfInterest: $productIds === [] ? null : $productIds,
            // User directive 2026-07-23: the opportunity inherits the lead's
            // Sede operativa (plain default, never BR-2-locked).
            operationalSiteId: $defaults->values['operational_site_id'],
            // User directive 2026-07-27: the "Note generali" are seeded from
            // the lead's own notes (plain default, never BR-2-locked).
            generalNotes: $defaults->values['general_notes'],
        ), $actor);

        // Step 4 (D-6/AC-067): reject a `single`-managed derivation carrying
        // 2+ products of interest — see class docblock for why this runs
        // here rather than through the FormRequest-only ValidatesQuoteLines.
        $this->assertOfferLinesFitManagementMode($opportunity, $productIds);

        // Step 5 (D-3/D-7/AC-062, spec 0102 D-1/AC-020): with a known actor,
        // ALWAYS generate the single collegata Offerta — with one REVENUE
        // line per product of interest, or zero lines when there are none.
        $this->createOfferForActor($opportunity, $productIds, $actor);

        return $opportunity;
    }

    /**
     * @throws ValidationException more than one distinct product of interest on a `single`-managed derivation
     */
    private function assertOfferLinesFitManagementMode(Opportunity $opportunity, array $productIds): void
    {
        if (count($productIds) < 2) {
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
     * @param  array<int, int>  $productIds  possibly empty (spec 0102 D-1):
     *                                       ProductOfferLineResolver::resolve()
     *                                       returns [] for [], so the Offerta
     *                                       is created with zero offer lines
     *                                       rather than skipped.
     */
    private function createOfferForActor(Opportunity $opportunity, array $productIds, ?User $actor): void
    {
        if ($actor === null) {
            return;
        }

        $this->quoteService->create(new CreateQuoteData(
            code: null,
            title: $opportunity->name,
            opportunityId: $opportunity->id,
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: $this->offerLineResolver->resolve($productIds),
        ), $actor);
    }

    /**
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function toProductLineAttributes(array $productLines): array
    {
        return array_map(static fn (array $line): array => [
            'business_function_id' => $line['business_function']['id'],
            'product_category_id' => $line['product_category']['id'],
        ], $productLines);
    }
}
