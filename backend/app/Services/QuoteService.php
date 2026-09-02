<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Commissions\QuoteLineCommissionWriter;
use App\Services\Concerns\GeneratesSequentialCode;
use App\Services\Contracts\ContractLifecycleManager;
use App\Services\Opportunities\OpportunityTitleBuilder;
use App\Services\Opportunities\RewardAssignmentWriter;
use App\Services\Quotes\QuoteAttributeValueWriter;
use App\Services\Quotes\QuoteLineCoverageWriter;
use App\Services\Quotes\QuoteManagerInheritance;
use App\Services\Quotes\QuoteManagerWriter;
use App\Services\Quotes\QuoteTotalsCalculator;
use App\Services\Quotes\QuoteWorkflowStatusAssigner;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `quotes` resource (spec 0065): create/update (with
 * the server-generated QUO-0001 code, D-13; the Opportunity snapshot of the
 * 3 roles plus the sede operativa, D-3), the full-replace line sync per tab
 * (D-8), the REVENUE-only opportunity coverage (D-7), the resolved working
 * status (spec 0083, D-1/D-8), and the persisted, always-recalculated
 * economic aggregates (D-9).
 *
 * Also hooks the Contract lifecycle automation (spec 0072, BR-1): every
 * create/update calls ContractLifecycleManager INSIDE this same transaction,
 * right after the quote's status is persisted, comparing its
 * WorkflowStatusGroup before/after the write.
 */
class QuoteService
{
    use GeneratesSequentialCode;

    private const string CODE_PREFIX = 'QUO';

    private const string CODE_TABLE = 'quotes';

    private const string CODE_COLUMN = 'code';

    /**
     * Relations eager-loaded for the detail read tree (QuoteResource), so a
     * single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        // Spec 0087, D-8: deepened from a bare `opportunity` to cover
        // QuoteManagerLabelResolver's fallback source (an Offerta with no
        // revenue line yet reads its Opportunita's own product lines'
        // categories) — `offerLines.product.category` below covers the
        // resolver's PRIMARY source.
        'opportunity.productLines.productCategory',
        // Richiesta utente 2026-08-31: il dettaglio Offerta mostra
        // l'anagrafica e il referente del record padre
        // (QuoteResource::registry/referent), che sono una PROIEZIONE
        // dell'Opportunita' — nessuna colonna propria su `quotes`.
        'opportunity.registry',
        'opportunity.referent',
        // Richiesta utente 2026-08-31: fonte, funzioni aziendali/categorie
        // prodotto e note generali del record padre, mostrate nel Contesto
        // dell'Offerta. `productLines.productCategory` e' gia' sopra.
        'opportunity.source',
        'opportunity.productLines.businessFunction',
        'quoteWorkflowStatus',
        'commercial',
        'reporter',
        'supervisor',
        // Spec 0087, D-3/T-04: the Offerta's own "Gestori Account"
        // (QuoteResource::summarizeManagers).
        'managers',
        'company',
        'companySite',
        // The site has no own name: its label is composed from the primary
        // address + city (OperationalSiteLabel), so both are eager-loaded.
        'operationalSite.addresses.city',
        'layout',
        'paymentMethod',
        'offerLines.product.category',
        // Spec 0099, D-5: the typology is read live through the product by
        // QuoteLineResource, so it is eager-loaded like the category above.
        'offerLines.product.productTypology',
        'offerLines.quote',
        'offerLines.vatRate',
        'offerLines.commissions.recipient',
        // Spec 0088, AC-053: both the frozen unit AND the product's current
        // one are eager-loaded, so QuoteLineResource's fallback (a historic
        // line with a NULL unit_of_measure_id) never N+1s.
        'offerLines.unitOfMeasure',
        'offerLines.product.unitOfMeasure',
        // Spec 0059 D-3 (Offerta origin): the reward chips the edit form
        // rehydrates its "abbinamento buono" control from.
        'rewards.rewardType',
        'costLines.product.category',
        'costLines.product.productTypology',
        'costLines.quote',
        'costLines.vatRate',
        'costLines.commissions.recipient',
        'costLines.unitOfMeasure',
        'costLines.product.unitOfMeasure',
    ];

    public function __construct(
        private readonly QuoteLineCoverageWriter $lineCoverageWriter,
        private readonly QuoteTotalsCalculator $totalsCalculator,
        private readonly QuoteLineCommissionWriter $commissionWriter,
        private readonly ContractLifecycleManager $contractLifecycleManager,
        private readonly OpportunityTitleBuilder $titleBuilder,
        private readonly QuoteWorkflowStatusAssigner $workflowStatusAssigner,
        private readonly QuoteAttributeValueWriter $attributeValueWriter,
        private readonly RewardAssignmentWriter $rewardAssignmentWriter,
        private readonly QuoteManagerWriter $managerWriter,
        private readonly QuoteManagerInheritance $managerInheritance,
    ) {}

    public function loadDetail(Quote $quote): Quote
    {
        return $quote->load(self::DETAIL_RELATIONS);
    }

    /**
     * The next sequential code (QUO-0001...) as a non-binding suggestion for
     * the create form's auto-fill (D-13). Lock-free: the binding value is
     * still resolved atomically in create().
     */
    public function previewNextCode(): string
    {
        return $this->peekNextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
    }

    /**
     * Create a new quote. A manual `code` (D-13) is persisted as submitted;
     * otherwise one is generated inside the transaction with a pessimistic
     * lock, so two concurrent creates never collide.
     */
    public function create(CreateQuoteData $data, User $actor): Quote
    {
        $quote = DB::transaction(function () use ($data, $actor): Quote {
            // Step 1: resolve the opportunity and snapshot its 3 commercial
            // roles (D-3) for every one of them the client did NOT submit.
            $opportunity = Opportunity::findOrFail($data->opportunityId);
            $attributes = $this->applySnapshotDefaults($data, $opportunity);

            // Step 2: resolve `layout_id` (D-3/D-8) — NOT an Opportunity
            // snapshot, a separate mechanism (see resolveLayoutId()).
            $attributes['layout_id'] = $this->resolveLayoutId($data);

            // Step 3: `code`/`quote_workflow_status_id` are deliberately
            // absent from Quote's #[Fillable] (D-13 / spec 0083 D-1) — the
            // status is bootstrapped with the GLOBAL default's `open` row so
            // the NOT NULL insert succeeds; Step 6 below resolves the FINAL
            // one, once the criteria it depends on (the REVENUE offer lines,
            // D-7) are persisted.
            $quote = new Quote($attributes);
            $quote->code = $data->code ?? $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $quote->quote_workflow_status_id = $this->workflowStatusAssigner->globalDefaultOpenStatusId();
            $quote->save();

            // Step 3b (spec 0087, D-4/D-5): the Offerta's own Gestori
            // Account — a submitted set wins outright, otherwise PREFILL
            // from the Opportunity's own GA at the same positions.
            $this->managerWriter->sync(
                $quote,
                $data->hasManagerSlots() ? $data->managerSlots : $this->managerInheritance->fromOpportunity($opportunity),
                $data->promoteManagersToOpportunity,
            );

            // Step 4: write the submitted line sets (full-replace, D-8) and
            // cover the opportunity for REVENUE lines only (D-7).
            $this->lineCoverageWriter->writeSubmitted($quote, $opportunity, $data->offerLines, $data->costLines);

            // Step 4b (spec 0084, D-5): "Informazioni aggiuntive" — written
            // AFTER the offer lines, so the applicable set validated against
            // is the one THOSE lines' categories produce, exactly the set the
            // create form rendered its fields from.
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($quote, $data->attributeValues, $changed, $old);
                $quote->save();
            }

            // Step 4c (spec 0059 D-3, Offerta origin): `reporter_id` is part
            // of the insert above, so a sync here already targets the right
            // beneficiary — no retarget() step, unlike update().
            if ($data->hasRewards()) {
                $this->rewardAssignmentWriter->sync($quote, $data->rewards);
            }

            // Step 5: persist the recalculated aggregates (D-9).
            $this->persistAggregates($quote);

            // Step 5b: re-derive the opportunity's name from its quotes'
            // revenue lines (spec 0077, D-3/D-4).
            $this->recalculateOpportunityName($opportunity->id);

            // Step 6 (spec 0083, AC-020/021/023-025): resolve the offer's
            // working status now that its criteria are final; an explicit
            // client choice advances FROM that resolved baseline, note-gated
            // when its destination `requires_note`.
            $this->workflowStatusAssigner->assign($quote, $data->workflowStatusId, $data->note, $actor);
            $quote->save();

            // Step 7: Contract lifecycle automation (spec 0072, BR-1) — a
            // fresh quote never had a prior status group.
            $this->contractLifecycleManager->syncOnStatusChange($quote, previousStatusId: null);

            return $quote;
        });

        return $this->loadDetail($quote);
    }

    /**
     * Update an existing quote. Only the submitted scalar keys are touched
     * (partial PATCH); `opportunity_id`/`code` never reach $data (rejected
     * upstream as immutable, AC-025/AC-069). A line set is full-replaced
     * ONLY when its own key was submitted (AC-036/037); the aggregates are
     * ALWAYS recalculated, even on a scalar-only PATCH (AC-041).
     */
    public function update(Quote $quote, UpdateQuoteData $data, User $actor): Quote
    {
        DB::transaction(function () use ($quote, $data, $actor): void {
            // Captured BEFORE the write (spec 0072, BR-1): the quote's
            // status group as persisted right now, needed to detect a
            // closed_won transition either way once this method has saved.
            $previousStatusId = $quote->quote_workflow_status_id;

            // Unconditional save: mirrors OpportunityService/ProjectService's
            // own update() — a clean save runs no UPDATE query.
            $quote->fill($data->submittedAttributes())->save();

            // Spec 0059 D-3/AC-022: a genuine `reporter_id` change retargets
            // every existing reward row of THIS offer, whether or not
            // `rewards` itself travelled in the same request.
            if ($data->reporterIdSubmitted && $quote->wasChanged('reporter_id')) {
                $this->rewardAssignmentWriter->retarget($quote);
            }

            if ($data->hasRewards()) {
                $this->rewardAssignmentWriter->sync($quote, $data->rewards);
            }

            // Spec 0087, AC-003/D-6: omitted leaves the Offerta's GA
            // untouched; a submitted array (even []) is an authoritative
            // full-replace via the sole writer.
            if ($data->hasManagerSlots()) {
                $this->managerWriter->sync($quote, $data->managerSlots, $data->promoteManagersToOpportunity);
            }

            $this->lineCoverageWriter->writeSubmitted(
                $quote,
                $data->hasOfferLines() ? Opportunity::findOrFail($quote->opportunity_id) : null,
                $data->offerLines,
                $data->costLines,
            );

            if ($data->commercialIdSubmitted || $data->reporterIdSubmitted || $data->supervisorIdSubmitted) {
                $quote->offerLines()->get()->each(
                    fn ($line) => $this->commissionWriter->sync($line, null),
                );
            }

            // "Informazioni aggiuntive" (spec 0084, D-5): validated against
            // the applicable set as it is AFTER any submitted `offer_lines`
            // replace it above — i.e. the set the form rendered its fields
            // from — then merged sparsely (a code the map leaves out keeps
            // its persisted value).
            if ($data->attributeValues !== null) {
                $changed = [];
                $old = [];
                $this->attributeValueWriter->apply($quote, $data->attributeValues, $changed, $old);
                $quote->save();
            }

            $this->persistAggregates($quote);

            // Re-derive the opportunity's name (spec 0077, D-3/D-4) — always,
            // mirroring persistAggregates(): even a scalar-only PATCH must see
            // the current line set (a prior write may have changed it).
            $this->recalculateOpportunityName($quote->opportunity_id);

            // spec 0083 (AC-020..026): re-resolve the baseline against the
            // (possibly changed by a submitted `offer_lines`) criteria, then
            // apply an explicit client choice on top, note-gated.
            $this->workflowStatusAssigner->assign($quote, $data->workflowStatusIdSubmitted ? $data->workflowStatusId : null, $data->note, $actor);
            $quote->save();

            // Contract lifecycle automation (spec 0072, BR-1).
            $this->contractLifecycleManager->syncOnStatusChange($quote, $previousStatusId);
        });

        return $this->loadDetail($quote);
    }

    /**
     * Delete the quote. `quote_lines` cascade away via their own FK (AC-026);
     * the linked Opportunity/Product/VatRate rows are untouched. The
     * opportunity's derived name (spec 0077) is recalculated AFTER the
     * cascade, inside the same transaction, so a now-orphaned revenue line
     * never counts (AC-034: falls back to `OPP_{id}` once no offer is left).
     *
     * Guarded (spec 0093, D-5): a quote with at least one WorkOrder cannot be
     * deleted — `work_orders.quote_id` is `restrictOnDelete`, so without this
     * explicit check the FK constraint would surface as an unhandled 500
     * instead of a clean 409. Checked via a plain query, not a Quote::
     * workOrders() relation (spec 0093 deliberately adds no such relation to
     * this file — the sole cross-module edit is this guard).
     */
    public function delete(Quote $quote): void
    {
        if (WorkOrder::where('quote_id', $quote->id)->exists()) {
            abort(409, 'This quote has work orders and cannot be deleted.');
        }

        DB::transaction(function () use ($quote): void {
            $opportunityId = $quote->opportunity_id;

            $quote->delete();

            $this->recalculateOpportunityName($opportunityId);
        });
    }

    /**
     * Overwrite $attributes' D-3 snapshot fields with the Opportunity's
     * CURRENT value for every one NOT submitted by the client (AC-020); a
     * submitted value — even null — always wins (AC-021).
     *
     * `operational_site_id` (user directive 2026-07-30) is inherited on the
     * same terms as the 3 commercial roles: the Opportunity owns one (spec
     * 0056) and a new quote starts from it, then diverges freely.
     * `company_id`/`company_site_id` are NOT here: the Opportunity has no
     * such columns to inherit from.
     *
     * `supervisor_id` is a plain copy like the other three (user directive
     * 2026-08-31, superseding 2026-08-06): it is no longer filtered to the
     * opportunity's Gestori Account. That filter silently produced null for
     * every opportunity whose Supervisore held no GA slot — the whole
     * dataset — so the documented inheritance never actually fired.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applySnapshotDefaults(CreateQuoteData $data, Opportunity $opportunity): array
    {
        $attributes = $data->attributes();

        $attributes['commercial_id'] = $data->commercialIdSubmitted ? $data->commercialId : $opportunity->commercial_id;
        $attributes['reporter_id'] = $data->reporterIdSubmitted ? $data->reporterId : $opportunity->reporter_id;
        $attributes['supervisor_id'] = $data->supervisorIdSubmitted
            ? $data->supervisorId
            : $opportunity->supervisor_id;
        $attributes['operational_site_id'] = $data->operationalSiteIdSubmitted
            ? $data->operationalSiteId
            : $opportunity->operational_site_id;

        return $attributes;
    }

    /**
     * `layout_id` resolution at create time (spec 0070, D-3/D-8). Deliberately
     * NOT part of applySnapshotDefaults(): `opportunities` has no `layout_id`
     * to copy from — the default read here comes from DocumentLayout, never
     * from $opportunity. A submitted key (even null) wins outright; otherwise
     * fall back to the `quotes` module's active default, or null when none
     * exists (AC-211). A minimal, explicit query: no suitable read method
     * exists yet on DocumentLayoutDefaultManager, which only covers the
     * WRITE-side invariant (spec 0069).
     */
    private function resolveLayoutId(CreateQuoteData $data): ?int
    {
        if ($data->layoutIdSubmitted) {
            return $data->layoutId;
        }

        return DocumentLayout::query()
            ->where('module', DocumentLayoutModule::Quotes->value)
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('id');
    }

    /**
     * Recalculates and persists the 5 header aggregates (D-9) from the
     * lines currently in the database — always, independent of which (if
     * any) line set this write actually touched. The columns are outside
     * Quote's #[Fillable] (D-9), so they are written via forceFill(),
     * mirroring OpportunityService's own `name` assignment.
     */
    private function persistAggregates(Quote $quote): void
    {
        $totals = $this->totalsCalculator->aggregates($quote);

        $quote->forceFill([
            'revenue_net' => $totals['revenue_net'],
            'revenue_vat' => $totals['revenue_vat'],
            'cost_net' => $totals['cost_net'],
            'cost_vat' => $totals['cost_vat'],
            'margin_net' => $totals['margin_net'],
        ])->save();
    }

    /**
     * Re-derives and persists `opportunities.name` (spec 0077, D-3/D-4) from
     * a fresh read of the opportunity — never the caller's own (possibly
     * stale, possibly relation-less) instance, so this stays correct whether
     * called from create/update/delete. `name` is never a client input
     * (AC-037): forceFill mirrors OpportunityService::create()'s own
     * assignment of the same column.
     */
    private function recalculateOpportunityName(int $opportunityId): void
    {
        $opportunity = Opportunity::findOrFail($opportunityId);

        $opportunity->forceFill(['name' => $this->titleBuilder->build($opportunity)])->save();
    }
}
