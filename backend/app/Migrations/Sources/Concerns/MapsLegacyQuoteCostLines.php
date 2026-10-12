<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\Quotes\QuoteLineData;
use App\Enums\ProductUsage;
use App\Enums\QuoteLineType;
use App\Migrations\Sources\QuotesSource;
use App\Migrations\Support\CostProductCatalogue;
use App\Models\Product;
use App\Models\Registry;
use App\Models\VatRate;

/**
 * Legacy offer costs (`quotationexpensereports`) to COST QuoteLineData (spec
 * 0189, G-13). The product is the migrated expense report (old_source =
 * expense_reports). A cost is imputed to the revenue line the legacy pairs it
 * with (same service), by its position in the same create; that line dropped
 * or absent -> a generic cost. The legacy supplier is never written (the
 * product carries it): a mismatch is only reported.
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
            $line = $this->legacyCostLine((array) $externalCostLine, count($lines), $legacyLineIds, $warnings);

            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $externalLine
     * @param  array<int, int>  $legacyLineIds
     * @param  array<int, string>  $warnings
     */
    private function legacyCostLine(array $externalLine, int $sortOrder, array $legacyLineIds, array &$warnings): ?QuoteLineData
    {
        $lineRef = $externalLine['id'] ?? '?';
        $product = $this->costProduct($externalLine['product_id'] ?? null, $lineRef, $warnings);

        if ($product === null) {
            return null;
        }

        $this->reportSupplierMismatch($product, $externalLine['supplier_id'] ?? null, $lineRef, $warnings);

        $quantity = $externalLine['quantity'] ?? null;

        if ($quantity === null || $quantity === '') {
            $warnings[] = "Cost line {$lineRef}: quantity missing, set to ".self::DEFAULT_QUANTITY.'.';
            $quantity = self::DEFAULT_QUANTITY;
        }

        $description = trim((string) ($externalLine['description'] ?? ''));

        return new QuoteLineData(
            productId: $product->id,
            quantity: (float) $quantity,
            unitPrice: (float) ($externalLine['unit_price'] ?? 0),
            vatRateId: $this->remapLegacyId(VatRate::class, $externalLine['vat_rate_id'] ?? null, "cost line {$lineRef} vat_rate_id", $warnings),
            sortOrder: $sortOrder,
            additionalDescription: $description !== '' ? $description : null,
            hasAdditionalDescription: true,
            offerLineIndex: $this->imputedOfferLineIndex($externalLine['offer_line_id'] ?? null, $legacyLineIds, $lineRef, $warnings),
        );
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function costProduct(mixed $externalRef, int|string $lineRef, array &$warnings): ?Product
    {
        if ($externalRef === null || $externalRef === '' || (int) $externalRef === 0) {
            $warnings[] = "Cost line {$lineRef} skipped: it has no product.";

            return null;
        }

        $product = Product::query()
            ->where('old_source', CostProductCatalogue::EXPENSE_REPORTS)
            ->where('old_id', $externalRef)
            ->first(['id', 'usages', 'supplier_id']);

        if ($product === null) {
            $warnings[] = "Cost line {$lineRef} skipped: cost product (legacy expense report id {$externalRef}) not migrated.";

            return null;
        }

        if (! $product->isUsableAs(ProductUsage::forLineType(QuoteLineType::Cost))) {
            $warnings[] = "Cost line {$lineRef} skipped: product (legacy expense report id {$externalRef}) is not usable as a cost.";

            return null;
        }

        return $product;
    }

    /**
     * The supplier lives on the product: a legacy line naming another one (or
     * a product without any) is reported, never written.
     *
     * @param  array<int, string>  $warnings
     */
    private function reportSupplierMismatch(Product $product, mixed $externalSupplierId, int|string $lineRef, array &$warnings): void
    {
        if ($externalSupplierId === null || $externalSupplierId === '' || (int) $externalSupplierId === 0) {
            return;
        }

        $productSupplierOldId = $product->supplier_id === null
            ? null
            : Registry::query()->whereKey($product->supplier_id)->value('old_id');

        if ($productSupplierOldId !== null && (int) $productSupplierOldId === (int) $externalSupplierId) {
            return;
        }

        $warnings[] = $productSupplierOldId === null
            ? "Cost line {$lineRef}: legacy supplier (legacy id {$externalSupplierId}) differs from the product's: the product has no supplier."
            : "Cost line {$lineRef}: legacy supplier (legacy id {$externalSupplierId}) differs from the product's (legacy id {$productSupplierOldId}).";
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
