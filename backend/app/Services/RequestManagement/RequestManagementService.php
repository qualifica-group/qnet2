<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\PersonalData\CreatePersonalData;
use App\DataObjects\Users\AddressInput;
use App\DataObjects\Users\ContactInput;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\RequestManagement\RequestAttributeResolver;
use App\Services\Opportunities\ProductCategoryCoherence;
use App\Services\Quotes\QuoteAttributeValueWriter;
use App\Services\Quotes\QuoteWorkflowStatusWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the request-management work panel (spec 0049; migrated
 * onto the Quote by spec 0086, D-2): a grid row IS a Quote, read/written
 * through this dedicated service rather than QuoteService/OpportunityService
 * — the operative endpoints have their OWN authorization
 * (`request-management.*`) and their own write rules.
 *
 * Each field lands on the model it lives on (D-2): "Fonte", planned
 * callback, the product-line classification and the client anagraphic block
 * are Opportunity-level (`quote.opportunity`); "Segnalatore", "Sede
 * operativa", the GA2 "Operatore", the reward assignments and the
 * offer's own REVENUE rows are Quote-level (D-3/D-4/D-7; spec 0087, D-9 for
 * the GA2 Operatore).
 *
 * This class ORCHESTRATES that sequence; the rules of each block live in a
 * writer of its own (RequestAttributionWriter, RequestProductLineWriter,
 * RequestOfferLineWriter, RequestClientProfileWriter) — the file had reached
 * the size ceiling (engineering.md §6) and the split follows the blocks the
 * panel itself is made of. `loadWorkPanel()`/`updateWork()` both return the
 * SAME shape — {quote} — consumed directly by RequestManagementResource, so
 * show/update render identically (data contract: "Response identica alla
 * GET").
 *
 * Activity logging (D-9): the module's operational history stays anchored on
 * the OPPORTUNITY. `next_callback_at` is excluded from Opportunity::$fillable
 * (mass-assignment guard) and `reporter_id`/`operational_site_id` — though
 * fillable on Quote — would otherwise log under the Quote's OWN activity
 * trail (a different resource than `request-management`'s, which reads the
 * Opportunity's thread). Both classes of field are therefore written with
 * the owning model's automatic log suspended where needed and reported into
 * an EXPLICIT `activity()` call carrying the same `attributes`/`old`
 * property shape the automatic log would have produced, so GET
 * /api/activity-log/request-management/{opportunity_id} still sees every
 * operative change (AC-039).
 */
final class RequestManagementService
{
    /**
     * Relations the work panel needs, eager-loaded in one shot (N+1-free):
     * contacts hang off each side's PersonalData card (HasPersonalData ->
     * HasContacts), never directly off Registry/Referent.
     *
     * @var array<int, string>
     */
    private const array WORK_PANEL_RELATIONS = [
        'opportunity.registry.personalData.contacts',
        // The client's comuni of birth and residence, shown in the identity block.
        'opportunity.registry.personalData.birthCity',
        'opportunity.registry.personalData.residenceCity',
        // The client's address is edited inline in the panel's "anagrafica"
        // section, with its geo names hydrated for the cascading selects.
        'opportunity.registry.personalData.addresses.city',
        'opportunity.registry.personalData.addresses.province',
        'opportunity.registry.personalData.addresses.state',
        'opportunity.registry.personalData.addresses.country',
        'opportunity.referent.personalData.contacts',
        'opportunity.commercial',
        // Attribution block (user directive 2026-07-22): "Fonte" stays on the
        // Opportunity (D-2); "Segnalatore"/Sede operativa/GA2 Operatore ride on
        // the Quote itself, below.
        'opportunity.source',
        'opportunity.quotes.quoteWorkflowStatus',
        'opportunity.productLines.businessFunction',
        'opportunity.productLines.productCategory',
        'reporter',
        // Spec 0056: the Sede operativa, also in the attribution block — the
        // site has no own name, its label composed from the primary address.
        'operationalSite.addresses.city',
        // Spec 0079/0086 D-6: the origin Sede of a transfer, same
        // composed-label need — eager-loaded so RequestManagementResource
        // never lazy-loads it (Model::preventLazyLoading() outside
        // production).
        'transferredFromOperationalSite.addresses.city',
        // Spec 0087, D-9: the GA2 Operatore, now a real FK on `quotes` —
        // `quotes.supervisor_id` is no longer read by this panel at all
        // (INV-5).
        'operator',
        // Spec 0086, D-7: "Linee dell'offerta" — the Offerta's own REVENUE
        // lines, replacing "prodotti di interesse" in this module's panel and
        // editable from it since the user directive 2026-08-07. Also one half
        // of RequestAttributeResolver's category union (D-1). `quote`/
        // `vatRate`/`commissions.recipient` are what QuoteLineResource (the
        // projection this panel now shares with the Offerte module) reads.
        'offerLines.product.category',
        'offerLines.quote',
        'offerLines.vatRate',
        'offerLines.commissions.recipient',
        // "Stato di lavorazione" (user directive 2026-08-07): the Offerta's
        // own current working-state row, projected by the resource.
        'quoteWorkflowStatus',
    ];

    public function __construct(
        private readonly RequestClientProfileWriter $clientProfileWriter,
        private readonly ProductCategoryCoherence $coherence,
        private readonly RequestProductLineWriter $productLineWriter,
        private readonly RequestOfferLineWriter $offerLineWriter,
        private readonly RequestAttributionWriter $attributionWriter,
        private readonly RequestAttributeResolver $attributeResolver,
        private readonly QuoteAttributeValueWriter $attributeValueWriter,
        private readonly QuoteWorkflowStatusWriter $workflowStatusWriter,
    ) {}

    /**
     * @return array{quote: Quote}
     */
    public function loadWorkPanel(Quote $quote): array
    {
        $quote->loadMissing(self::WORK_PANEL_RELATIONS);

        return ['quote' => $quote];
    }

    /**
     * Applies the sparse PATCH payload (spec 0049 data_contract: only the
     * submitted keys change) and returns the SAME work-panel shape as
     * loadWorkPanel(), post-save.
     *
     * @param  array{next_callback_at?: string|null, product_lines?: array<int, array{business_function_id: int, product_category_id: int}>, offer_lines?: array<int, array<string, mixed>>, source_id?: int|null, reporter_id?: int|null, operator_id?: int|null, operational_site_id?: int|null, rewards?: array<int, array{reward_type_id: int}>, attribute_values?: array<string, mixed>, quote_workflow_status_id?: int|null, note?: string|null, client_identity?: CreatePersonalData, client_contacts?: array<int, ContactInput>, client_address?: AddressInput}  $data
     * @return array{quote: Quote}
     */
    public function updateWork(Quote $quote, User $actor, array $data): array
    {
        return DB::transaction(function () use ($quote, $actor, $data): array {
            $changed = [];
            $old = [];
            $opportunity = $this->resolveOpportunity($quote);
            // spec 0059, D-3/AC-024: captured BEFORE this write mutates
            // reporter_id in-memory, so applyRewards() can tell a genuine
            // change apart from an untouched/no-op submission.
            $previousReporterId = $quote->reporter_id;

            // Step 0: attribution — "Fonte" on the Opportunity (D-2),
            // "Segnalatore"/Sede operativa on the Quote (D-3/D-4).
            $this->attributionWriter->applySource($opportunity, $data);
            $this->attributionWriter->applyQuoteAttribution($quote, $data, $changed, $old);

            // Step 1: funzione aziendale + categoria prodotto (user directive
            // 2026-07-31), still an Opportunity-level classification (D-2).
            if (array_key_exists('product_lines', $data)) {
                $this->productLineWriter->apply($opportunity, (array) $data['product_lines'], $changed, $old);
            }

            // Step 1-bis: the coherence rule (user directive 2026-07-31) —
            // every product of interest the Opportunity already carries must
            // stay inside a category the (possibly just-replaced) product
            // lines cover. `products_of_interest` itself is no longer
            // writable from this module (AC-022), so only a `product_lines`
            // change can trigger this.
            $this->assertProductCategoryCoherence($opportunity, $data);

            // Step 1-ter: "Linee dell'offerta" (user directive 2026-08-07) —
            // AFTER the product lines (a replaced classification is what the
            // rows are covered against) and BEFORE the two steps that depend
            // on them: the applicable attribute set unions their categories
            // (D-1) and the workflow set resolves on them (spec 0083).
            if (array_key_exists('offer_lines', $data)) {
                $this->offerLineWriter->apply($quote, $actor, (array) $data['offer_lines'], $changed, $old);
            }

            // Step 2: next planned callback (spec 0052 D-1/D-4) — sparse:
            // key absent leaves the persisted value untouched, `null` clears
            // it. A real value change also zeroes the reminder marker so a
            // rescheduled date is not skipped by the future reminder job.
            if (array_key_exists('next_callback_at', $data)) {
                $this->applyNextCallbackAt($opportunity, $data['next_callback_at'], $changed, $old);
            }

            // Step 2-bis: "Informazioni aggiuntive" (user directive
            // 2026-08-07) — AFTER the product lines, so the set the values are
            // validated against is the one the panel will render next, not the
            // pre-PATCH one (OpportunityProductLineWriter::sync() already
            // unsets the stale relation). Reuses the Offerte writer verbatim,
            // fed THIS module's applicable set (D-1).
            if (array_key_exists('attribute_values', $data)) {
                $this->attributeValueWriter->apply(
                    $quote,
                    (array) $data['attribute_values'],
                    $changed,
                    $old,
                    $this->attributeResolver->resolve($quote),
                );
            }

            // Step 2-ter: "Stato di lavorazione" (user directive 2026-08-07).
            $this->applyWorkflowStatus($quote, $actor, $data, $changed, $old);

            $opportunity->save();
            $quote->save();

            // Step 3: the GA2 "Operatore" (spec 0087, D-9) — a pivot row plus
            // a Quote column, written after both models are saved.
            if (array_key_exists('operator_id', $data)) {
                $this->attributionWriter->applyOperator($quote, $data['operator_id'], $actor, $changed, $old);
            }

            // Step 4: reward assignments (spec 0059, AC-023; D-4/D-12) —
            // identical semantics to the opportunities payload: the retarget
            // half runs whenever `reporter_id` genuinely changed, INDEPENDENT
            // of whether `rewards` itself was submitted; the sync half only
            // when `rewards` was submitted. The owner is now the Quote.
            $this->attributionWriter->applyRewards($quote, $previousReporterId, $data, $changed, $old);

            // Step 5: client anagraphic (spec 0049 amendment; spec 0055 D-7
            // for the inline channel's four sparse single-field keys) —
            // identity, contacts and address land on the Registry's
            // PersonalData card via the Opportunity, not on the Quote.
            $this->clientProfileWriter->applyTo($opportunity, $data, $changed, $old);

            // Step 6: explicit activity entry, anchored on the OPPORTUNITY
            // (D-9) — see class docblock.
            $this->logOperationalChange($opportunity, $actor, $changed, $old);

            return $this->loadWorkPanel($quote);
        });
    }

    /**
     * The Offerta's working-status advance from this panel (user directive
     * 2026-08-07), through the SAME choke point the quotes module uses: it
     * enforces the resolved set (spec 0083 AC-021) and creates the transition
     * note a `requires_note` destination demands (AC-023/024/025), inside this
     * service's transaction.
     *
     * Sparse like every other key, and `null` is NOT a clear: an Offerta
     * always carries a working state (QuoteService bootstraps it at
     * creation), so "no value submitted" is the only meaning null can have
     * here. The change is mirrored into the caller's audit arrays because
     * this module reads the OPPORTUNITY's activity thread (D-9), which the
     * Quote's own model log never reaches.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException the target is outside the resolved workflow, or it requires a note and none was given
     */
    private function applyWorkflowStatus(Quote $quote, User $actor, array $data, array &$changed, array &$old): void
    {
        if (! array_key_exists('quote_workflow_status_id', $data) || $data['quote_workflow_status_id'] === null) {
            return;
        }

        $previousStatusId = $quote->quote_workflow_status_id;

        $this->workflowStatusWriter->apply(
            $quote,
            (int) $data['quote_workflow_status_id'],
            $actor,
            $data['note'] ?? null,
        );

        if ($quote->quote_workflow_status_id === $previousStatusId) {
            return;
        }

        $old['quote_workflow_status_id'] = $previousStatusId;
        $changed['quote_workflow_status_id'] = $quote->quote_workflow_status_id;
        // The projection the panel re-renders from is the relation, not the
        // column: a stale loaded copy would send back the PREVIOUS status.
        $quote->unsetRelation('quoteWorkflowStatus');
    }

    /**
     * Explicit query when the relation is not already loaded — never a bare
     * lazy-loaded property access, mirroring RequestOperatorWriter's own
     * discipline (Model::preventLazyLoading() outside production).
     */
    private function resolveOpportunity(Quote $quote): Opportunity
    {
        if ($quote->relationLoaded('opportunity')) {
            return $quote->opportunity;
        }

        return $quote->opportunity()->firstOrFail();
    }

    /**
     * The coherence rule (user directive 2026-07-31), checked against the
     * Opportunity's PERSISTED products of interest — this module no longer
     * writes that collection (AC-022), so only a `product_lines` change can
     * orphan one of them.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function assertProductCategoryCoherence(Opportunity $opportunity, array $data): void
    {
        if (! array_key_exists('product_lines', $data)) {
            return;
        }

        $productIds = $opportunity->productsOfInterest()->pluck('products.id')->map(intval(...))->all();

        $this->coherence->assert(
            $productIds,
            $opportunity->productLines()->pluck('product_category_id')->map(intval(...))->all(),
            'product_lines',
            ProductCategoryCoherence::REQUEST_MESSAGE,
        );
    }

    /**
     * `next_callback_at` (spec 0052 D-1/D-2): NOT in Opportunity::$fillable,
     * assigned directly here (never mass-assigned). $value is whatever the
     * request submitted — a date string or null — and the 'datetime' cast
     * normalizes it as soon as it is set, so both sides of the comparison
     * below read back through the SAME cast (D-4 invariant: the reminder
     * marker is zeroed if and only if the resolved instant actually changes).
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function applyNextCallbackAt(Opportunity $opportunity, mixed $value, array &$changed, array &$old): void
    {
        $previous = $opportunity->next_callback_at;
        $opportunity->next_callback_at = $value;
        $current = $opportunity->next_callback_at;

        if ($this->callbackInstantKey($previous) === $this->callbackInstantKey($current)) {
            return;
        }

        $old['next_callback_at'] = $this->callbackInstantKey($previous);
        $changed['next_callback_at'] = $this->callbackInstantKey($current);
        $opportunity->next_callback_reminded_at = null;
    }

    /**
     * A comparable/loggable representation of a `next_callback_at` instant —
     * null-safe, so two nulls compare equal without a Carbon method call.
     */
    private function callbackInstantKey(?Carbon $value): ?string
    {
        return $value?->format('Y-m-d\TH:i');
    }

    /**
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    private function logOperationalChange(Opportunity $opportunity, User $actor, array $changed, array $old): void
    {
        if ($changed === []) {
            return;
        }

        activity($opportunity->getTable())
            ->performedOn($opportunity)
            ->causedBy($actor)
            ->event('updated')
            ->withProperties(['attributes' => $changed, 'old' => $old])
            ->log('Request management work update');
    }
}
