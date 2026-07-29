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
     * net (D-5) and MAY be negative (AC-043, no clamp).
     *
     * @return array{revenue_net: float, revenue_vat: float, cost_net: float, cost_vat: float, margin_net: float}
     */
    public function aggregates(Quote $quote): array
    {
        $revenue = $this->sumLines($quote, QuoteLineType::Revenue);
        $cost = $this->sumLines($quote, QuoteLineType::Cost);

        return [
            'revenue_net' => $revenue['net'],
            'revenue_vat' => $revenue['vat'],
            'cost_net' => $cost['net'],
            'cost_vat' => $cost['vat'],
            'margin_net' => round($revenue['net'] - $cost['net'], 2, PHP_ROUND_HALF_UP),
        ];
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
