<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\CategoryManagementMode;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Services\Opportunities\OpportunityProductLineCoverage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        return [
            $field => ['sometimes', 'array', 'max:200'],
            "{$field}.*.product_id" => ['required', 'integer', Rule::exists('products', 'id')],
            "{$field}.*.id" => ['nullable', 'integer', Rule::exists('quote_lines', 'id')],
            // gt:0 (AC-034: 0 and negative rejected); decimal:0,2 caps the
            // input to <= 2 decimal places (AC-032), the SAME rounding scale
            // QuoteTotalsCalculator freezes on the row.
            "{$field}.*.quantity" => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999.99'],
            // unit_price 0 IS accepted (AC-034); negative is not.
            "{$field}.*.unit_price" => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            "{$field}.*.vat_rate_id" => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            "{$field}.*.sort_order" => ['nullable', 'integer', 'min:0'],
            "{$field}.*.net_amount" => ['prohibited'],
            "{$field}.*.vat_amount" => ['prohibited'],
            "{$field}.*.total_amount" => ['prohibited'],
            "{$field}.*.commissions" => $field === 'offer_lines'
                ? ['sometimes', 'array', 'max:4']
                : ['prohibited'],
            "{$field}.*.commissions.*.id" => ['nullable', 'integer', Rule::exists('quote_line_commissions', 'id')],
            "{$field}.*.commissions.*.recipient_role" => ['required', Rule::enum(CommissionRecipientRole::class), 'distinct'],
            "{$field}.*.commissions.*.recipient_type" => ['required', Rule::in(['referent', 'user', 'registry'])],
            "{$field}.*.commissions.*.recipient_id" => ['required', 'integer'],
            "{$field}.*.commissions.*.commission_type" => ['required', Rule::enum(CommissionType::class)],
            "{$field}.*.commissions.*.value" => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            "{$field}.*.commissions.*.calculated_amount" => ['prohibited'],
            "{$field}.*.commissions.*.internal_note" => ['nullable', 'string', 'max:5000'],
            "{$field}.*.commissions.*.origin" => ['required', Rule::enum(CommissionOrigin::class)],
            "{$field}.*.commissions.*.commission_configuration_id" => ['nullable', 'integer', Rule::exists('commission_configurations', 'id')],
        ];
    }
}
