<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\ProductUsage;
use App\Enums\QuoteLineType;
use App\Migrations\Sources\QuotesSource;
use App\Migrations\Support\CostProductCatalogue;
use App\Models\Product;
use App\Models\VatRate;
use RuntimeException;

/**
 * Legacy offer costs to COST QuoteLineData (spec 0189, G-13): expense reports
 * and employee costs. The product is the migrated cost product of the legacy
 * table named by `product_source` (`expense_reports` when absent,
 * `user_levels` for an employee cost). A cost is imputed to the revenue line the legacy pairs it
 * with (same service), by its position in the same create; that line absent
 * -> a generic cost. A cost without a usable migrated cost product fails the
 * whole offer (user decision 2026-10-14). The row's supplier is the product's default
 * (AssignsDefaultProductSuppliers); the legacy supplier is only reported when it differs.
 *
 * @phpstan-require-extends QuotesSource
 */
trait MapsLegacyQuoteCostLines
{
    /**
     * @param  array<int, int>  $legacyLineIds  legacy revenue line id keyed by the sort_order (= index) it is created at
     * @param  array<int, string>  $warnings
     * @return array<int, QuoteLineData>
     */
    private function legacyCostLines(mixed $externalCostLines, array $legacyLineIds, array &$warnings): array
    {
        $lines = [];

        foreach ((array) ($externalCostLines ?? []) as $externalCostLine) {
            $lines[] = $this->legacyCostLine((array) $externalCostLine, count($lines), $legacyLineIds, $warnings);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $externalLine
     * @param  array<int, int>  $legacyLineIds
     * @param  array<int, string>  $warnings
     */
    private function legacyCostLine(array $externalLine, int $sortOrder, array $legacyLineIds, array &$warnings): QuoteLineData
    {
        $lineRef = $externalLine['id'] ?? '?';
        $product = $this->costProduct($externalLine['product_source'] ?? CostProductCatalogue::EXPENSE_REPORTS, $externalLine['product_id'] ?? null, $lineRef);

        $description = trim((string) ($externalLine['description'] ?? ''));

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) ($externalLine['quantity'] ?? 0),
            unitPrice: (float) ($externalLine['unit_price'] ?? 0),
            vatRateId: $this->remapLegacyId(VatRate::class, $externalLine['vat_rate_id'] ?? null, "cost line {$lineRef} vat_rate_id", $warnings),
            sortOrder: $sortOrder,
            additionalDescription: $description !== '' ? $description : null,
            hasAdditionalDescription: true,
            offerLineIndex: $this->imputedOfferLineIndex($externalLine['offer_line_id'] ?? null, $legacyLineIds, $lineRef, $warnings),
        );
    }

    private function costProduct(mixed $source, mixed $externalRef, int|string $lineRef): Product
    {
        if (! array_key_exists((string) $source, CostProductCatalogue::BRANCHES)
            || $externalRef === null || $externalRef === '' || (int) $externalRef === 0) {
            throw new RuntimeException("Cost line {$lineRef} has no product; the quote is not imported.");
        }

        $product = Product::query()
            ->where('old_source', $source)
            ->where('old_id', $externalRef)
            ->first(['id', 'usages']);

        if ($product === null) {
            throw new RuntimeException("Cost line {$lineRef}: cost product ({$source} legacy id {$externalRef}) not migrated; the quote is not imported.");
        }

        if (! $product->isUsableAs(ProductUsage::forLineType(QuoteLineType::Cost))) {
            throw new RuntimeException("Cost line {$lineRef}: product ({$source} legacy id {$externalRef}) is not usable as a cost; the quote is not imported.");
        }

        return $product;
    }

    /**
     * @param  array<int, int>  $legacyLineIds
     * @param  array<int, string>  $warnings
     */
    private function imputedOfferLineIndex(mixed $externalOfferLineId, array $legacyLineIds, int|string $lineRef, array &$warnings): ?int
    {
        if ($externalOfferLineId === null || $externalOfferLineId === '') {
            return null;
        }

        $index = array_search((int) $externalOfferLineId, $legacyLineIds, true);

        if ($index === false) {
            $warnings[] = "Cost line {$lineRef}: its offer line (legacy id {$externalOfferLineId}) was not imported, kept as a generic cost.";

            return null;
        }

        return $index;
    }
}
