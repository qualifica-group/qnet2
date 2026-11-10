<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Sources\QuotesSource;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Services\Quotes\QuoteTotalsCalculator;
use Closure;

/**
 * Aligns the cost lines of an offer (spec 0375 D-12, D-13): they meet on product and
 * imputed revenue line, and only a legacy cost changed in the period can move anything.
 * The supplier stays the one on the row, as at the import.
 */
final class QuoteCostLineRealigner
{
    public function __construct(
        private readonly QuotesSource $source,
        private readonly QuoteTotalsCalculator $totals,
        private readonly CostRowMatcher $matcher,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $legacyCosts
     * @param  Closure(array<string, mixed>): bool  $isRecent  on the legacy cost
     * @return bool true when a row was written
     */
    public function handle(Quote $quote, array $legacyCosts, Closure $isRecent, LegacyRealignNotes $notes): bool
    {
        // Step 1: the legacy costs mapped by the import's own rules.
        $revenueIds = $quote->offerLines()->whereNotNull('old_id')->pluck('id', 'old_id')->map(intval(...))->all();
        $rows = array_map(fn (array $cost): array => $this->mappedRow($cost, $revenueIds, $notes), $legacyCosts);

        // Step 2: pair them with what is stored.
        $plan = $this->matcher->plan(
            $rows,
            $quote->costLines()->get(),
            static fn (array $row): string => self::key($row['data']->productId, $row['offer_line_id']),
            static fn (QuoteLine $line): string => self::key($line->product_id, $line->offer_line_id),
            $this->sameValues(...),
            static fn (array $row): bool => $isRecent($row['legacy']),
            static fn (QuoteLine $line): bool => true,
        );

        foreach ($plan['ambiguous'] as $row) {
            $notes->warn('Cost line '.($row['legacy']['id'] ?? '?').': more than one possible match on the offer; not updated.');
        }

        // Step 3: the writes.
        foreach ($plan['update'] as [$row, $line]) {
            $line->fill($this->values($row['data']))->saveQuietly();
        }

        foreach ($plan['create'] as $row) {
            $this->create($quote, $row);
        }

        return $plan['update'] !== [] || $plan['create'] !== [];
    }

    private static function key(int|string|null $productId, int|string|null $offerLineId): string
    {
        return "{$productId}|{$offerLineId}";
    }

    /**
     * @param  array<string, mixed>  $cost
     * @param  array<int, int>  $revenueIds  legacy line id => this offer's line id
     * @return array{legacy: array<string, mixed>, data: QuoteLineData, offer_line_id: int|null}
     */
    private function mappedRow(array $cost, array $revenueIds, LegacyRealignNotes $notes): array
    {
        $warnings = [];
        $data = $this->source->legacyCostLine([...$cost, 'offer_line_id' => null], 0, [], $warnings);
        $legacyOfferLineId = $cost['offer_line_id'] ?? null;
        $offerLineId = $legacyOfferLineId === null ? null : ($revenueIds[(int) $legacyOfferLineId] ?? null);

        if ($legacyOfferLineId !== null && $offerLineId === null) {
            $notes->warn('Cost line '.($cost['id'] ?? '?').": its offer line (legacy id {$legacyOfferLineId}) is not on this offer, treated as a generic cost.");
        }

        return ['legacy' => $cost, 'data' => $data, 'offer_line_id' => $offerLineId];
    }

    /**
     * @param  array{data: QuoteLineData}  $row
     */
    private function sameValues(array $row, QuoteLine $line): bool
    {
        return round($row['data']->quantity, 2) === round((float) $line->quantity, 2)
            && round($row['data']->unitPrice, 2) === round((float) $line->unit_price, 2)
            && (int) $row['data']->vatRateId === (int) $line->vat_rate_id
            && $row['data']->additionalDescription === $line->additional_description;
    }

    /**
     * @return array<string, mixed>
     */
    private function values(QuoteLineData $data): array
    {
        $rate = $data->vatRateId === null ? null : (float) VatRate::query()->whereKey($data->vatRateId)->value('rate');
        $amounts = $this->totals->lineAmounts($data->quantity, $data->unitPrice, $rate);

        return [
            'quantity' => $data->quantity,
            'unit_price' => $data->unitPrice,
            'vat_rate_id' => $data->vatRateId,
            'additional_description' => $data->additionalDescription,
            'net_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
        ];
    }

    /**
     * @param  array{data: QuoteLineData, offer_line_id: int|null}  $row
     */
    private function create(Quote $quote, array $row): void
    {
        $data = $row['data'];

        (new QuoteLine([
            ...$this->values($data),
            'quote_id' => $quote->id,
            'line_type' => QuoteLineType::Cost,
            'product_id' => $data->productId,
            'supplier_id' => ProductSupplier::query()->where('is_default', true)->where('product_id', $data->productId)->value('supplier_id'),
            'unit_of_measure_id' => Product::query()->whereKey($data->productId)->value('unit_of_measure_id'),
            'offer_line_id' => $row['offer_line_id'],
            'sort_order' => (int) $quote->costLines()->max('sort_order') + 1,
        ]))->saveQuietly();
    }
}
