<?php

declare(strict_types=1);

namespace App\Quotes;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use Illuminate\Validation\Rule;

/**
 * The per-row validation rules of a quote line collection (`offer_lines`,
 * `cost_lines`), as ONE definition shared by every channel that accepts them.
 *
 * Extracted out of `ValidatesQuoteLines` (which still owns the cross-row
 * rules — the `single`-category cap, the "at least one revenue row" gates —
 * and keeps delegating here for the per-row set) when the generic inline-edit
 * engine became a third entry point: `App\Services\Table\CellValueValidator`
 * is not a FormRequest and cannot use the trait, and a second copy of these
 * rules would be a standing invitation to fix a bound on one channel and
 * leave it wrong on the other.
 *
 * `line_type` is deliberately absent: it is stamped server-side by which
 * array a row came from, never accepted from the client. So are the three
 * amounts and `unit_of_measure_id` — server-computed/server-frozen, hence
 * `prohibited`.
 */
final class QuoteLineRules
{
    /** Backend per-collection row ceiling, mirrored by the frontend's `MAX_LINES_PER_TAB`. */
    public const int MAX_ROWS = 200;

    /**
     * @param  string  $field  the payload key the rows travel under (`offer_lines`, `cost_lines`, or `value` for a single inline cell)
     * @param  bool  $withCommissions  whether the row may carry the provvigioni block at all
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(string $field, bool $withCommissions): array
    {
        return [
            $field => ['sometimes', 'array', 'max:'.self::MAX_ROWS],
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
            // Spec 0088, D-5/AC-052: server-frozen from the Product at write
            // time (QuoteLineWriter::sync()), never accepted from the client
            // — same discipline as the three amount columns above.
            "{$field}.*.unit_of_measure_id" => ['prohibited'],
            "{$field}.*.commissions" => $withCommissions
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
