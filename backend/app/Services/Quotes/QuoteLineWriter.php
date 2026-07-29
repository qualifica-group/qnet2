<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;

/**
 * Full-replace writer for ONE `line_type` of a Quote's lines (spec 0065,
 * D-8): the Offerta and Costi tabs are synced INDEPENDENTLY — a call for
 * REVENUE never touches the existing COST rows and vice versa (AC-037).
 * Amounts are computed and frozen here (D-10/D-12) via
 * QuoteTotalsCalculator; VAT rates are batch-resolved up front so writing N
 * lines never costs N extra queries.
 */
final class QuoteLineWriter
{
    public function __construct(private readonly QuoteTotalsCalculator $calculator) {}

    /**
     * Deletes every existing row of $type on $quote and reinserts $lines in
     * order — an empty array azzera the set (AC-036). `sort_order` is the
     * one carried by the row, else its own index in $lines (AC-038).
     *
     * @param  array<int, QuoteLineData>  $lines
     */
    public function sync(Quote $quote, QuoteLineType $type, array $lines): void
    {
        // Step 1: drop the current rows of THIS type only.
        QuoteLine::query()->where('quote_id', $quote->id)->where('line_type', $type)->delete();

        // Step 2: batch-resolve the VAT rates the submitted rows reference.
        $rates = $this->resolveVatRates($lines);

        // Step 3: reinsert, amounts frozen at write time (D-10/D-12).
        foreach (array_values($lines) as $index => $line) {
            $rate = $line->vatRateId !== null ? ($rates[$line->vatRateId] ?? null) : null;
            $amounts = $this->calculator->lineAmounts($line->quantity, $line->unitPrice, $rate);

            $quote->lines()->create([
                'line_type' => $type,
                'product_id' => $line->productId,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'vat_rate_id' => $line->vatRateId,
                'net_amount' => $amounts['net'],
                'vat_amount' => $amounts['vat'],
                'total_amount' => $amounts['total'],
                'sort_order' => $line->sortOrder ?? $index,
            ]);
        }

        $quote->unsetRelations();
    }

    /**
     * @param  array<int, QuoteLineData>  $lines
     * @return array<int, float> vat_rate_id => rate
     */
    private function resolveVatRates(array $lines): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (QuoteLineData $line): ?int => $line->vatRateId,
            $lines,
        ))));

        if ($ids === []) {
            return [];
        }

        return VatRate::query()
            ->whereIn('id', $ids)
            ->pluck('rate', 'id')
            ->map(static fn (mixed $rate): float => (float) $rate)
            ->all();
    }
}
