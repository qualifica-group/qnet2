<?php

namespace App\Migrations\Sources\Concerns;

use App\DataObjects\Quotes\QuoteLineData;
use App\Migrations\Sources\QuotesSource;
use App\Models\ProductSupplier;
use App\Models\Registry;

/**
 * Stamps every legacy offer line (REVENUE and COST) with the default supplier
 * its product has at migration time, null without one (spec 0320, D-6): one
 * batch query for the whole offer. The legacy supplier of a cost line is only
 * reported when it differs from that default, never written.
 *
 * @phpstan-require-extends QuotesSource
 */
trait AssignsDefaultProductSuppliers
{
    /**
     * @param  array<int, QuoteLineData>  $lines
     * @param  array<int, QuoteLineData>  $costLines
     * @param  array<int, mixed>  $externalCostLines  the legacy cost rows, same order as $costLines
     * @param  array<int, string>  $warnings
     * @return array{0: array<int, QuoteLineData>, 1: array<int, QuoteLineData>}
     */
    private function withDefaultSuppliers(array $lines, array $costLines, array $externalCostLines, array &$warnings): array
    {
        $productIds = array_values(array_unique(array_map(
            static fn (QuoteLineData $line): int => $line->productId,
            [...$lines, ...$costLines],
        )));

        if ($productIds === []) {
            return [$lines, $costLines];
        }

        $defaults = ProductSupplier::query()
            ->where('is_default', true)
            ->whereIn('product_id', $productIds)
            ->pluck('supplier_id', 'product_id')
            ->map(static fn (mixed $supplierId): int => (int) $supplierId)
            ->all();

        $oldIds = Registry::query()->whereIn('id', array_values($defaults))->pluck('old_id', 'id')->all();

        foreach ($costLines as $index => $costLine) {
            $externalLine = (array) ($externalCostLines[$index] ?? []);
            $defaultSupplierId = $defaults[$costLine->productId] ?? null;

            $this->reportSupplierMismatch(
                $externalLine['supplier_id'] ?? null,
                $defaultSupplierId === null ? null : ($oldIds[$defaultSupplierId] ?? null),
                $externalLine['id'] ?? '?',
                $warnings,
            );
        }

        return [
            array_map(fn (QuoteLineData $line): QuoteLineData => $this->withSupplier($line, $defaults[$line->productId] ?? null), $lines),
            array_map(fn (QuoteLineData $line): QuoteLineData => $this->withSupplier($line, $defaults[$line->productId] ?? null), $costLines),
        ];
    }

    private function withSupplier(QuoteLineData $line, ?int $supplierId): QuoteLineData
    {
        return new QuoteLineData(
            productId: $line->productId,
            quantity: $line->quantity,
            unitPrice: $line->unitPrice,
            vatRateId: $line->vatRateId,
            sortOrder: $line->sortOrder,
            id: $line->id,
            commissions: $line->commissions,
            additionalDescription: $line->additionalDescription,
            hasAdditionalDescription: $line->hasAdditionalDescription,
            offerLineId: $line->offerLineId,
            offerLineIndex: $line->offerLineIndex,
            supplierId: $supplierId,
            hasSupplier: true,
            keepsUnitPrice: true,
        );
    }

    /**
     * @param  array<int, string>  $warnings
     */
    private function reportSupplierMismatch(mixed $externalSupplierId, mixed $defaultSupplierOldId, int|string $lineRef, array &$warnings): void
    {
        if ($externalSupplierId === null || $externalSupplierId === '' || (int) $externalSupplierId === 0) {
            return;
        }

        if ($defaultSupplierOldId !== null && (int) $defaultSupplierOldId === (int) $externalSupplierId) {
            return;
        }

        $warnings[] = $defaultSupplierOldId === null
            ? "Cost line {$lineRef}: legacy supplier (legacy id {$externalSupplierId}) differs from the product's: the product has no default supplier."
            : "Cost line {$lineRef}: legacy supplier (legacy id {$externalSupplierId}) differs from the product's default (legacy id {$defaultSupplierOldId}).";
    }
}
