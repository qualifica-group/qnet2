<?php

declare(strict_types=1);

namespace App\Migrations\Support;

use App\Migrations\Support\Concerns\BuildsLegacyDocumentLines;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseRequestLine;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Services\PurchaseOrders\PurchaseOrderAmountCalculator;
use App\Services\PurchaseOrders\PurchaseRequestLineLinker;

/**
 * Writes the lines of a freshly imported legacy ODA (spec 0296) with qnet-2
 * amounts (PurchaseOrderAmountCalculator) and the totals, links the migrated
 * RDA lines (`oda_reference` through PurchaseRequestLineLinker, D-4) and
 * writes the receipt of a fulfilled order (D-5, as the spec 0254 backfill).
 * The caller runs without activity log and nothing is notified.
 */
final class LegacyPurchaseOrderLineWriter
{
    use BuildsLegacyDocumentLines;

    public const string RECEIPT_NOTE = 'Ricezione generata dalla migrazione';

    public function __construct(
        private readonly PurchaseOrderAmountCalculator $amounts,
        private readonly PurchaseRequestLineLinker $linker,
        private readonly LegacyCostTypeLookup $costTypes,
    ) {}

    /**
     * @param  array<int, mixed>  $legacyLines
     * @param  array<int, string>  $warnings
     */
    public function write(PurchaseOrder $order, array $legacyLines, array &$warnings): void
    {
        $rates = $this->vatRates($legacyLines);
        $requestLineCodes = [];

        foreach ($legacyLines as $legacyLine) {
            $legacyLine = (array) $legacyLine;

            // Step 1: the line with qnet-2 amounts.
            $line = $this->createLine($order, $legacyLine, $rates, $warnings);

            if ($line->purchase_request_line_id !== null) {
                $requestLineCodes[$line->purchase_request_line_id] = $order->code();
            }
        }

        // Step 2: the totals and the RDA lines now held by this order.
        $this->amounts->refreshTotals($order);
        $this->linker->link($requestLineCodes);
    }

    /**
     * One receipt with every ordered quantity (D-5), dated on the legacy last update.
     */
    public function receiveAll(PurchaseOrder $order, string $receiptDate): void
    {
        $receipt = new PurchaseOrderReceipt(['receipt_date' => $receiptDate, 'notes' => self::RECEIPT_NOTE]);
        $receipt->forceFill(['purchase_order_id' => $order->id, 'created_by' => $order->created_by])->saveQuietly();

        foreach ($order->lines()->get() as $line) {
            $receipt->lines()->create(['purchase_order_line_id' => $line->id, 'quantity' => $line->quantity]);
            $line->forceFill(['received_quantity' => $line->quantity])->saveQuietly();
        }
    }

    /**
     * @param  array<string, mixed>  $legacyLine
     * @param  array<int, string>  $rates  vat_rate_id => rate
     * @param  array<int, string>  $warnings
     */
    private function createLine(PurchaseOrder $order, array $legacyLine, array $rates, array &$warnings): PurchaseOrderLine
    {
        $lineId = $legacyLine['id'] ?? '?';
        $vatRateId = $this->optionalOldId(VatRate::class, $legacyLine['vat_rate_id'] ?? null, "Line {$lineId}: VAT rate", $warnings);
        $amounts = $this->amounts->line([
            'quantity' => $legacyLine['quantity'] ?? 0,
            'unit_price' => $legacyLine['unit_price'] ?? 0,
            'vat_rate_id' => $vatRateId,
        ], $rates);

        $line = new PurchaseOrderLine([
            'position' => (int) ($legacyLine['position'] ?? 0),
            'description' => $this->lineDescription($legacyLine['description'] ?? null, "Line {$lineId}", $warnings),
            'cost_type_id' => $this->costTypes->idFor($legacyLine['cost_type_name'] ?? null, "Line {$lineId}"),
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'vat_rate_id' => $vatRateId,
            'work_order_id' => $this->optionalOldId(WorkOrder::class, $legacyLine['work_order_id'] ?? null, "Line {$lineId}: work order", $warnings),
        ]);
        $line->forceFill([
            'old_id' => $legacyLine['id'] ?? null,
            'purchase_order_id' => $order->id,
            'taxable_amount' => $amounts['taxable_amount'],
            'vat_amount' => $amounts['vat_amount'],
            'total_amount' => $amounts['total_amount'],
            'purchase_request_line_id' => $this->requestLineId($legacyLine['purchase_request_line_id'] ?? null, $lineId, $warnings),
        ])->saveQuietly();

        return $line;
    }

    /**
     * The migrated RDA line, unless another order line already holds it (the column is unique).
     *
     * @param  array<int, string>  $warnings
     */
    private function requestLineId(mixed $legacyId, int|string $lineId, array &$warnings): ?int
    {
        $id = $this->optionalOldId(PurchaseRequestLine::class, $legacyId, "Line {$lineId}: purchase request line", $warnings);

        if ($id !== null && PurchaseOrderLine::query()->where('purchase_request_line_id', $id)->exists()) {
            $warnings[] = "Line {$lineId}: purchase request line (legacy id {$legacyId}) already linked to another order, left empty.";

            return null;
        }

        return $id;
    }

    /**
     * @param  array<int, mixed>  $legacyLines
     * @return array<int, string> vat_rate_id => rate
     */
    private function vatRates(array $legacyLines): array
    {
        $legacyIds = array_filter(array_map(fn (mixed $line): mixed => ((array) $line)['vat_rate_id'] ?? null, $legacyLines));

        return VatRate::query()->whereIn('old_id', $legacyIds)->pluck('rate', 'id')
            ->map(fn (mixed $rate): string => (string) $rate)->all();
    }
}
