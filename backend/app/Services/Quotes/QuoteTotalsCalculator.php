<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Enums\QuoteLineType;
use App\Models\Quote;
use App\Models\QuoteLine;

/**
 * The single arithmetic authority for a Quote's amounts (spec 0065, D-5/D-9/
 * D-12): per-line `net_amount`/`vat_amount`/`total_amount`, and the 5
 * persisted header aggregates. Every value is rounded HALF-UP to 2 decimals
 * with PHP's own `round()` — NOT a naive float compare: since Laravel 5.3
 * `round()` pre-corrects the classic binary-representation error (verified
 * here: `round(2.675, 2)` gives `2.68`, not `2.67`), so it is safe at this
 * scale given every input is already constrained to <= 2 decimals by
 * validation (ValidatesQuoteLines). No bcmath dependency is introduced for
 * that reason.
 */
final class QuoteTotalsCalculator
{
    /**
     * One row's amounts from its raw quantity/unit_price and (nullable) VAT
     * rate percentage — D-12: `net_amount` = quantity * unit_price;
     * `vat_amount` = net_amount * rate / 100 (0.00 with no rate, AC-031);
     * `total_amount` = net_amount + vat_amount.
     *
     * @return array{net: float, vat: float, total: float}
     */
    public function lineAmounts(float $quantity, float $unitPrice, ?float $vatRate): array
    {
        $net = round($quantity * $unitPrice, 2, PHP_ROUND_HALF_UP);
        $vat = $vatRate === null ? 0.0 : round($net * $vatRate / 100, 2, PHP_ROUND_HALF_UP);
        $total = round($net + $vat, 2, PHP_ROUND_HALF_UP);

        return ['net' => $net, 'vat' => $vat, 'total' => $total];
    }

    /**
     * The 5 header aggregates (D-9), read fresh from the persisted lines:
     * the sum of each row's OWN already-rounded amount (D-12 — never a
     * re-derivation from quantity/price), so it stays correct whichever tab
     * was actually rewritten by this write. Margin is revenue net minus cost
     * net minus the total of every REVENUE line's commissions, all roles
     * (spec 0145, D-3/D-4 — supersedes spec 0065 D-5) and MAY be negative
     * (AC-043, no clamp). The caller MUST have already run
     * QuoteLineCommissionWriter::recalculateQuoteMargins() on $quote in this
     * same write, so `calculated_amount` reflects the current margin base.
     *
     * @return array{revenue_net: float, revenue_vat: float, cost_net: float, cost_vat: float, margin_net: float}
     */
    public function aggregates(Quote $quote): array
    {
        $revenue = $this->sumLines($quote, QuoteLineType::Revenue);
        $cost = $this->sumLines($quote, QuoteLineType::Cost);
        $commissions = $this->commissionTotal($quote);

        return [
            'revenue_net' => $revenue['net'],
            'revenue_vat' => $revenue['vat'],
            'cost_net' => $cost['net'],
            'cost_vat' => $cost['vat'],
            'margin_net' => round($revenue['net'] - $cost['net'] - $commissions, 2, PHP_ROUND_HALF_UP),
        ];
    }

    /**
     * Spec 0145, D-3/D-4: every role's commission on $quote's REVENUE lines,
     * summed in ONE query — the total feeding `margin_net` is net of ALL of
     * them, independent of which roles the current actor may see
     * (QuoteResource's permission gate only hides the per-role breakdown,
     * never this total, D-7).
     */
    private function commissionTotal(Quote $quote): float
    {
        return round((float) QuoteLine::query()
            ->where('quote_id', $quote->id)
            ->where('line_type', QuoteLineType::Revenue)
            ->join('quote_line_commissions', 'quote_lines.id', '=', 'quote_line_commissions.quote_line_id')
            ->sum('quote_line_commissions.calculated_amount'), 2, PHP_ROUND_HALF_UP);
    }

    /**
     * @return array{net: float, vat: float}
     */
    private function sumLines(Quote $quote, QuoteLineType $type): array
    {
        $query = QuoteLine::query()->where('quote_id', $quote->id)->where('line_type', $type);

        return [
            'net' => round((float) (clone $query)->sum('net_amount'), 2, PHP_ROUND_HALF_UP),
            'vat' => round((float) (clone $query)->sum('vat_amount'), 2, PHP_ROUND_HALF_UP),
        ];
    }
}
