<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use App\Migrations\Realign\LegacyRealignNotes;
use App\Migrations\Support\LegacyCostTypeLookup;
use App\Migrations\Support\LegacyPurchaseOrderLineWriter;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseRequestLine;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\PurchaseOrders\PurchaseOrderAmountCalculator;
use App\Services\PurchaseOrders\PurchaseRequestLineLinker;

/**
 * Aligns the lines of an ODA by `old_id` (spec 0375 D-11). A quantity below what
 * was already received is not written: the receipt is a fact on qnet-2.
 */
final class PurchaseOrderLineAligner
{
    public function __construct(
        private readonly LegacyPurchaseOrderLineWriter $writer,
        private readonly PurchaseOrderAmountCalculator $amounts,
        private readonly PurchaseRequestLineLinker $linker,
        private readonly LegacyCostTypeLookup $costTypes,
        private readonly PurchaseLegacyReferences $references,
    ) {}

    /**
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, string>  $warnings
     * @return bool whether any line changed; the order totals are then saved
     */
    public function align(PurchaseOrder $order, array $legacyLines, array &$warnings, LegacyRealignNotes $notes): bool
    {
        // Step 1: split the legacy lines into existing and new.
        $existing = $order->lines()->whereNotNull('old_id')->get()->keyBy(fn (PurchaseOrderLine $line): string => (string) $line->old_id);
        $rates = $this->writer->vatRates($legacyLines);
        $newLines = [];
        $linked = [];
        $changed = false;

        foreach ($legacyLines as $legacyLine) {
            $legacyLine = (array) $legacyLine;
            $line = $existing->pull((string) ($legacyLine['id'] ?? ''));

            if ($line === null) {
                $newLines[] = $legacyLine;

                continue;
            }

            $changed = $this->updateLine($line, $legacyLine, $rates, $linked, $warnings, $notes) || $changed;
        }

        // Step 2: what the legacy no longer sends stays (D-9).
        foreach ($existing as $line) {
            $notes->warn("Line {$line->old_id} is no longer in the legacy; kept.");
        }

        if ($newLines === [] && ! $changed) {
            return false;
        }

        // Step 3: RDA links just set, then new lines and the totals through the import's writer.
        $this->linker->link(array_fill_keys($linked, $order->code()));
        $this->writer->write($order, $newLines, $warnings);

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @param  array<int, string>  $rates
     * @param  array<int, int>  $linked  RDA lines newly held by this order
     * @param  array<int, string>  $warnings
     */
    private function updateLine(PurchaseOrderLine $line, array $legacyLine, array $rates, array &$linked, array &$warnings, LegacyRealignNotes $notes): bool
    {
        $lineId = $line->old_id;
        $vatRateId = $this->references->optionalOrCurrent(VatRate::class, $legacyLine['vat_rate_id'] ?? null, $line->vat_rate_id, "Line {$lineId}: VAT rate", $warnings);
        $rates += $vatRateId === null ? [] : [$vatRateId => (string) VatRate::query()->whereKey($vatRateId)->value('rate')];
        $amounts = $this->amounts->line([
            'quantity' => $this->quantity($line, $legacyLine, $notes),
            'unit_price' => $legacyLine['unit_price'] ?? 0,
            'vat_rate_id' => $vatRateId,
        ], $rates);

        $line->forceFill([
            'position' => (int) ($legacyLine['position'] ?? 0),
            'description' => $this->writer->lineDescription($legacyLine['description'] ?? null, "Line {$lineId}", $warnings),
            'cost_type_id' => $this->costTypes->idFor($legacyLine['cost_type_name'] ?? null, "Line {$lineId}"),
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $vatRateId,
            'work_order_id' => $this->references->optionalOrCurrent(WorkOrder::class, $legacyLine['work_order_id'] ?? null, $line->work_order_id, "Line {$lineId}: work order", $warnings),
            'taxable_amount' => $amounts['taxable_amount'],
            'vat_amount' => $amounts['vat_amount'],
            'total_amount' => $amounts['total_amount'],
            'purchase_request_line_id' => $this->requestLineId($line, $legacyLine['purchase_request_line_id'] ?? null, $linked, $warnings),
        ]);

        if (! $line->isDirty()) {
            return false;
        }

        $line->saveQuietly();

        return true;
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     */
    private function quantity(PurchaseOrderLine $line, array $legacyLine, LegacyRealignNotes $notes): mixed
    {
        $quantity = $legacyLine['quantity'] ?? 0;

        if (bccomp((string) $quantity, (string) $line->received_quantity, 3) < 0) {
            $notes->warn("Line {$line->old_id}: legacy quantity {$quantity} is below the {$line->received_quantity} already received; kept.");

            return $line->quantity;
        }

        return $quantity;
    }

    /**
     * The link already held stays; a new one follows the import's uniqueness rule.
     * A link the legacy drops stays: nothing here unlinks an RDA line.
     *
     * @param  array<int, int>  $linked
     * @param  array<int, string>  $warnings
     */
    private function requestLineId(PurchaseOrderLine $line, mixed $legacyId, array &$linked, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return $line->purchase_request_line_id;
        }

        $target = PurchaseRequestLine::query()->where('old_id', $legacyId)->value('id');

        if ($target !== null && (int) $target === $line->purchase_request_line_id) {
            return $line->purchase_request_line_id;
        }

        $id = $this->writer->requestLineId($legacyId, $line->old_id, $warnings);

        if ($id === null) {
            return $line->purchase_request_line_id;
        }

        $linked[] = $id;

        return $id;
    }
}
