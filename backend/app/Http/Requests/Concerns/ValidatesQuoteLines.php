<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

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
     * @return array<string, array<int, mixed>>
     */
    private function quoteLineFieldRules(string $field): array
    {
        return [
            $field => ['sometimes', 'array', 'max:200'],
            "{$field}.*.product_id" => ['required', 'integer', Rule::exists('products', 'id')],
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
        ];
    }
}
