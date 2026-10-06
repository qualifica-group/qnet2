<?php

namespace App\Migrations\Support;

use App\Models\Quote;
use App\Models\QuoteLine;
use App\Services\Commissions\QuoteLineCommissionWriter;
use App\Services\Quotes\QuoteTotalsCalculator;

/**
 * Copies an offer line already programmed into another work order (spec 0189,
 * G-10, user decision 2026-10-02): a legacy offer line may feed several
 * commesse (renewal cycles, quantity planning, duplicates), qnet-2 programs a
 * line into one work order only. The copy carries the same product, amounts
 * and commissions — the legacy paid commissions per commessa — and no
 * `old_id` (that anchor stays on the original). The offer totals and margin
 * are recomputed with the same calculators QuoteService uses.
 */
final class QuoteLineDuplicator
{
    public function __construct(
        private readonly QuoteLineCommissionWriter $commissionWriter,
        private readonly QuoteTotalsCalculator $totalsCalculator,
    ) {}

    public function duplicate(QuoteLine $line): QuoteLine
    {
        // Step 1: the copy, appended after the offer's last line.
        $copy = $line->replicate(['old_id']);
        $copy->sort_order = (int) QuoteLine::query()->where('quote_id', $line->quote_id)->max('sort_order') + 1;
        $copy->save();

        // Step 2: the same commissions on the copy.
        foreach ($line->commissions as $commission) {
            $commission->replicate()->fill(['quote_line_id' => $copy->id])->save();
        }

        // Step 3: the offer margin and totals now include the copy.
        $this->recalculateTotals($line->quote);

        return $copy;
    }

    private function recalculateTotals(Quote $quote): void
    {
        $this->commissionWriter->recalculateQuoteMargins($quote);

        $quote->forceFill($this->totalsCalculator->aggregates($quote))->saveQuietly();
    }
}
