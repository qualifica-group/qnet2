<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\QuoteLineType;
use App\Enums\SupplierCommissionDirection;
use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Sources\QuotesSource;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\VatRate;
use App\Services\Commissions\QuoteLineCommissionWriter;
use App\Services\Quotes\QuoteTotalsCalculator;
use App\Services\Quotes\SupplierCommissionEnablement;
use Carbon\CarbonImmutable;

/**
 * Aligns the revenue and cost lines of an offer (spec 0375 D-12): only rows changed
 * in the legacy since the date move, because older rows carry price differences that
 * come from the import conversion. A row already invoiced stays; a row gone from the
 * legacy stays and is reported. Written directly: QuoteLineWriter is a full replace
 * that refuses every row held by a commessa.
 */
final class QuoteLineRealigner
{
    public function __construct(
        private readonly QuotesSource $source,
        private readonly QuoteTotalsCalculator $totals,
        private readonly QuoteLineCommissionWriter $commissions,
        private readonly SupplierCommissionEnablement $enablement,
        private readonly QuoteCostLineRealigner $costLines,
    ) {}

    /**
     * @param  array<string, mixed>  $legacy  the legacy offer
     */
    public function handle(Quote $quote, array $legacy, string $since, LegacyRealignNotes $notes): void
    {
        $from = CarbonImmutable::parse($since)->startOfDay();
        $isRecent = static fn (array $row): bool => filled($row['updated_at'] ?? null) && CarbonImmutable::parse($row['updated_at'])->greaterThanOrEqualTo($from);

        // Step 1: revenue rows, then cost rows.
        $changed = $this->alignRevenue($quote, array_values((array) ($legacy['lines'] ?? [])), $isRecent, $notes);
        $changed = $this->costLines->handle($quote, array_values((array) ($legacy['cost_lines'] ?? [])), $isRecent, $notes) || $changed;

        if (! $changed) {
            return;
        }

        // Step 2: the header amounts, in the order the offer itself computes them.
        $this->commissions->recalculateQuoteMargins($quote);
        $quote->forceFill([
            ...$this->totals->aggregates($quote),
            ...(filled($legacy['updated_at'] ?? null) ? ['updated_at' => CarbonImmutable::parse($legacy['updated_at'])] : []),
        ])->saveQuietly();
    }

    /**
     * @param  list<array<string, mixed>>  $legacyLines
     * @return bool true when a row was written
     */
    private function alignRevenue(Quote $quote, array $legacyLines, callable $isRecent, LegacyRealignNotes $notes): bool
    {
        $stored = $quote->offerLines()->whereNotNull('old_id')->get()->keyBy(static fn (QuoteLine $line): int => (int) $line->old_id);
        $this->reportMissing($stored->keys()->all(), $legacyLines, $notes);
        $changed = false;

        foreach ($legacyLines as $legacyLine) {
            if ($isRecent($legacyLine)) {
                $changed = $this->alignRevenueLine($quote, $stored->get((int) ($legacyLine['id'] ?? 0)), $legacyLine, $notes) || $changed;
            }
        }

        return $changed;
    }

    /**
     * @param  array<int, int>  $storedOldIds
     * @param  list<array<string, mixed>>  $legacyLines
     */
    private function reportMissing(array $storedOldIds, array $legacyLines, LegacyRealignNotes $notes): void
    {
        foreach (array_diff($storedOldIds, array_map(static fn (array $line): int => (int) ($line['id'] ?? 0), $legacyLines)) as $oldId) {
            $notes->warn("Line {$oldId} is no longer on the offer in the legacy; kept.");
        }
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     */
    private function alignRevenueLine(Quote $quote, ?QuoteLine $line, array $legacyLine, LegacyRealignNotes $notes): bool
    {
        $warnings = [];

        if ($line === null) {
            $this->createRevenueLine($quote, $this->source->legacyOfferLine($legacyLine, 0, $warnings), (int) $legacyLine['id']);
            array_map($notes->warn(...), $warnings);

            return true;
        }

        $data = $this->source->legacyOfferLine([...$legacyLine, 'commissions' => []], 0, $warnings);

        if ($this->sameValues($data, $line)) {
            return false;
        }

        if (InvoiceLine::query()->where('quote_line_id', $line->id)->exists()) {
            $notes->warn("Line {$line->old_id} is already invoiced; not updated.");

            return false;
        }

        $line->fill([...$this->lineValues($data), ...$this->productAttributes($line, $data)])->saveQuietly();
        array_map($notes->warn(...), $warnings);

        return true;
    }

    private function sameValues(QuoteLineData $data, QuoteLine $line): bool
    {
        return $data->productId === $line->product_id
            && round($data->quantity, 2) === round((float) $line->quantity, 2)
            && round($data->unitPrice, 2) === round((float) $line->unit_price, 2)
            && (int) $data->vatRateId === (int) $line->vat_rate_id
            && $data->additionalDescription === $line->additional_description;
    }

    /**
     * @return array<string, mixed>
     */
    private function lineValues(QuoteLineData $data): array
    {
        $rate = $data->vatRateId === null ? null : (float) VatRate::query()->whereKey($data->vatRateId)->value('rate');
        $amounts = $this->totals->lineAmounts($data->quantity, $data->unitPrice, $rate);

        return [
            'product_id' => $data->productId,
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
     * The values frozen on a row, only when its product changes (QuoteLineWriter's rule).
     *
     * @return array<string, mixed>
     */
    private function productAttributes(?QuoteLine $line, QuoteLineData $data): array
    {
        if ($line !== null && $line->product_id === $data->productId) {
            return [];
        }

        return [
            'supplier_id' => ProductSupplier::query()->where('is_default', true)->where('product_id', $data->productId)->value('supplier_id'),
            'unit_of_measure_id' => Product::query()->whereKey($data->productId)->value('unit_of_measure_id'),
            'supplier_commission_direction' => $this->enablement->enabledForProducts([$data->productId])[$data->productId] ?? false
                ? ($line?->supplier_commission_direction ?? SupplierCommissionDirection::Paid)
                : null,
        ];
    }

    private function createRevenueLine(Quote $quote, QuoteLineData $data, int $legacyId): void
    {
        $line = new QuoteLine([
            ...$this->lineValues($data),
            ...$this->productAttributes(null, $data),
            'quote_id' => $quote->id,
            'line_type' => QuoteLineType::Revenue,
            'sort_order' => (int) $quote->offerLines()->max('sort_order') + 1,
        ]);
        $line->forceFill(['old_id' => $legacyId])->saveQuietly();
        $this->commissions->sync($line, $data->commissions);
    }
}
