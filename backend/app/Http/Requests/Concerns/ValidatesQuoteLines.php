<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\CategoryManagementMode;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Quotes\QuoteLineRules;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared validation for a quote's `offer_lines`/`cost_lines` payload (spec
 * 0065, D-11): both tabs share the SAME row shape and the SAME rules —
 * `line_type` is never part of the payload, it is stamped server-side by
 * which array a row came from. Used verbatim by StoreQuoteRequest and
 * UpdateQuoteRequest (every field is `sometimes` on both: the array itself
 * is optional on create too, AC-030 fixtures aside).
 *
 * `net_amount`/`vat_amount`/`total_amount` are `prohibited` (AC-033): they
 * are server-computed (QuoteTotalsCalculator), never client input.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesQuoteLines
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function quoteLinesRules(): array
    {
        return array_merge(
            $this->quoteLineFieldRules('offer_lines'),
            $this->quoteLineFieldRules('cost_lines'),
        );
    }

    /**
     * The REVENUE tab alone, for a channel that writes the offer rows without
     * owning the commissions block (request-management, user directive
     * 2026-08-07): same row shape and same rules as the Offerte endpoints —
     * so a rule fixed on one channel cannot stay wrong on the other — with
     * `commissions` PROHIBITED. An absent key is what makes
     * QuoteLineCommissionWriter preserve the persisted overrides and only
     * recalculate their amounts, so this module never silently drops what the
     * Offerte form set up. The nested `commissions.*` rules stay inert behind
     * the prohibition.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function offerLinesOnlyRules(): array
    {
        return [
            ...$this->quoteLineFieldRules('offer_lines'),
            'offer_lines.*.commissions' => ['prohibited'],
        ];
    }

    /**
     * Spec 0077, user directive 2026-08-07: an opportunity whose product
     * category root is managed in `single` mode carries ONE product line
     * (INV-3) — and its offer carries ONE product row, for the same reason.
     * COST lines are untouched: they are internal cost items, not the sold
     * product (same asymmetry as the coverage rule, D-7).
     *
     * Only ever checked when `offer_lines` is actually SUBMITTED, and only
     * from the second row on: an update that leaves the collection untouched
     * keeps a historic non-conforming quote saveable on its other fields,
     * exactly like D-5's grandfathering on the opportunity side.
     */
    protected function enforceSingleOfferLine(Validator $validator, ?Quote $quote): void
    {
        $lines = $this->input('offer_lines');

        if (! is_array($lines) || count($lines) < 2) {
            return;
        }

        $opportunity = $this->offerLinesOpportunity($quote);

        if ($opportunity === null) {
            return;
        }

        if (app(OpportunityProductLineCoverage::class)->managementModeOf($opportunity) !== CategoryManagementMode::Single) {
            return;
        }

        $validator->errors()->add('offer_lines', __(OpportunityProductLineCoverage::SINGLE_OFFER_LINE_MESSAGE));
    }

    /**
     * Spec 0102 AC-001/002/003: the Offerte creation channel requires at
     * least one REVENUE row on every POST — an absent `offer_lines` key or
     * an empty array are both a violation. Deliberately NOT folded into
     * `quoteLineFieldRules()` (shared with request-management, which stays
     * unconstrained per spec 0086 AC-028): called from StoreQuoteRequest's
     * own `withValidator()` only.
     */
    protected function requireOfferLineOnCreate(Validator $validator): void
    {
        $this->assertOfferLineNotEmpty($validator);
    }

    /**
     * Spec 0102 D-2/AC-004/005/006: on update the rule fires ONLY when
     * `offer_lines` is actually SUBMITTED — an explicit full-replace to zero
     * rows. A key that is ABSENT leaves the collection untouched (the same
     * `offerLines !== null` semantics as `UpdateQuoteData::hasOfferLines()`)
     * and must stay silent: that grandfathering keeps a historic zero-line
     * Offerta saveable on its other fields.
     */
    protected function requireOfferLineOnUpdate(Validator $validator): void
    {
        if (! $this->has('offer_lines')) {
            return;
        }

        $this->assertOfferLineNotEmpty($validator);
    }

    private function assertOfferLineNotEmpty(Validator $validator): void
    {
        $lines = $this->input('offer_lines');

        if (is_array($lines) && $lines !== []) {
            return;
        }

        $validator->errors()->add('offer_lines', __('quotes.offer_line_required'));
    }

    /**
     * The opportunity the submitted offer lines hang from: the quote's own on
     * update (`opportunity_id` is `prohibited` there, AC-025), the submitted
     * id on create. `null` whenever it cannot be resolved — an invalid id is
     * already reported by its own `exists` rule.
     */
    private function offerLinesOpportunity(?Quote $quote): ?Opportunity
    {
        if ($quote !== null) {
            return $quote->opportunity;
        }

        $opportunityId = $this->input('opportunity_id');

        return is_numeric($opportunityId) ? Opportunity::find((int) $opportunityId) : null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function quoteLineFieldRules(string $field): array
    {
        // One definition for every channel (App\Quotes\QuoteLineRules): the
        // generic inline-edit engine validates the same rows without being a
        // FormRequest, and a second copy here would drift.
        return QuoteLineRules::fieldRules($field, withCommissions: $field === 'offer_lines');
    }
}
