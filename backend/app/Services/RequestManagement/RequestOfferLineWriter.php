<?php

declare(strict_types=1);

namespace App\Services\RequestManagement;

use App\DataObjects\Quotes\UpdateQuoteData;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Validation\ValidationException;

/**
 * "Linee dell'offerta" as edited from the work panel (user directive
 * 2026-08-07: "un componente dove si inseriscono le linee dell'offerta, il
 * componente prendi spunto da quello del form dell'offerta"). The rows are the
 * Offerta's own REVENUE lines — this module IS that Offerta since spec 0086 —
 * so the write is delegated to QuoteService::update() with a lines-only DTO,
 * exactly as RequestCreationService already consumes QuoteService::create().
 *
 * Delegating, not re-orchestrating, is the point: writing offer lines drags
 * four derived effects behind it — the opportunity product-line coverage
 * (spec 0065 D-7), the persisted economic aggregates (D-9), the derived
 * `opportunities.name` (spec 0077 D-3/D-4) and the re-resolution of the
 * Offerta's workflow set (spec 0083, whose criteria include those very lines).
 * A second implementation of that sequence would be four chances to drift.
 *
 * `commissions` never travel on this channel (ValidatesQuoteLines::
 * offerLinesOnlyRules() prohibits them): a row arriving without the key makes
 * QuoteLineCommissionWriter keep the persisted overrides and only recalculate
 * their amounts, so an offer set up in the Offerte module keeps its
 * provvigioni when an operator edits quantity or price from here.
 *
 * The diff below is what only this channel needs: the panel reports its
 * operative changes onto the OPPORTUNITY's activity thread (spec 0049 D-9),
 * which the Quote's own model log never reaches, and an unchanged collection
 * must neither be rewritten nor logged.
 *
 * Spec 0114, D-4/D-6: before the full-replace, a `simplified_offer_line`
 * classification has SimplifiedOfferLineNormalizer freeze quantity/unit-price/
 * VAT-rate on the rows that are new or whose product changed — the same
 * choke point every one of this module's write channels (panel, inline cell)
 * already shares.
 */
final class RequestOfferLineWriter
{
    public function __construct(
        private readonly QuoteService $quoteService,
        private readonly SimplifiedOfferLineNormalizer $normalizer,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $submitted  the validated `offer_lines` rows
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     *
     * @throws ValidationException a row's product is outside the covered categories, or its id belongs to another offer
     */
    public function apply(Quote $quote, User $actor, array $submitted, array &$changed, array &$old): void
    {
        // Step 1: compare against what is persisted, in the same comparable
        // shape — an untouched collection leaves the Offerta alone.
        $before = $this->snapshot($quote);

        // Step 1-bis: freeze the rows a simplified classification owns (D-4/D-6)
        // BEFORE the write, so QuoteService::update() never sees the client's
        // raw quantity/unit-price/VAT-rate for them.
        $submitted = $this->normalizer->normalizeForUpdate($quote, $submitted);

        // Step 2: full-replace through the Offerte service (see class doc).
        $this->quoteService->update($quote, UpdateQuoteData::fromValidated(['offer_lines' => $submitted]), $actor);

        // Step 3: the relation the panel re-renders from is now stale.
        $quote->unsetRelation('offerLines');
        $after = $this->snapshot($quote);

        if ($after === $before) {
            return;
        }

        $old['offer_lines'] = $before;
        $changed['offer_lines'] = $after;
    }

    /**
     * Deletes the rows whose product the Opportunity's (just replaced)
     * product lines no longer cover (bug 2026-10-05, user decision "cancella
     * le righe"): reached when the classification changes on a write that
     * does not carry `offer_lines` — the grid's category cell — so the offer
     * lands where the work panel lands when it empties those rows and saves.
     * Without it the next offer save would put the removed category back
     * (OpportunityProductLineCoverage). The kept rows are resubmitted as they
     * stand, ids included, through the same full-replace as apply(): their
     * provvigioni and additional description survive untouched.
     *
     * @param  array<string, mixed>  $changed
     * @param  array<string, mixed>  $old
     */
    public function dropUncovered(Quote $quote, Opportunity $opportunity, User $actor, array &$changed, array &$old): void
    {
        // Step 1: the categories the classification covers now.
        $coveredCategoryIds = $opportunity->productLines()->pluck('product_category_id')->map(intval(...))->all();

        // Step 2: the persisted rows, split by whether their product is still covered.
        $lines = $quote->offerLines()->with('product:id,category_id')->get();
        $kept = $lines->filter(
            static fn (QuoteLine $line): bool => in_array((int) $line->product?->category_id, $coveredCategoryIds, true),
        );

        if ($kept->count() === $lines->count()) {
            return;
        }

        // Step 3: rewrite with the covered rows only, in their own order.
        $this->apply($quote, $actor, $kept->values()->map(static fn (QuoteLine $line, int $index): array => [
            'id' => $line->id,
            'product_id' => $line->product_id,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'vat_rate_id' => $line->vat_rate_id,
            'sort_order' => $index,
        ])->all(), $changed, $old);
    }

    /**
     * The offer's rows as an ordered list of comparable scalars — what an
     * operator can actually change from this panel. Read with an explicit
     * query: Model::preventLazyLoading() is active outside production, and the
     * relation is deliberately unset between the two calls.
     *
     * @return array<int, array{product_id: int, quantity: string, unit_price: string, vat_rate_id: int|null}>
     */
    private function snapshot(Quote $quote): array
    {
        return $quote->offerLines()
            ->orderBy('sort_order')
            ->get()
            ->map(static fn (QuoteLine $line): array => [
                'product_id' => $line->product_id,
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'vat_rate_id' => $line->vat_rate_id,
            ])
            ->all();
    }
}
