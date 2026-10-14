<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Services\PurchaseOrders\PurchaseOrderAmountCalculator;
use App\Support\Amounts\LineAmountCalculator;

/**
 * Server-side amounts of a payable document (spec 0250, D-5; spec 0253): the
 * shared engine with 3-decimal quantity, a null VAT rate = 0 and the VAT
 * computed once per rate (as the electronic invoice), the header being the
 * exact sum of the lines.
 */
final class PurchaseInvoiceAmountCalculator
{
    private const int QUANTITY_SCALE = 3;

    public function __construct(
        private readonly PurchaseOrderAmountCalculator $orderAmounts,
        private readonly LineAmountCalculator $engine,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  validated payload lines
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     */
    public function compute(array $lines): array
    {
        $lines = array_map(fn (array $line): array => $line + ['vat_rate_id' => null], array_values($lines));
        $rates = $this->orderAmounts->vatRates($lines);
        $document = $this->engine->documentPerRate(
            array_map(fn (array $line): array => [
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'vat_rate' => $rates[$line['vat_rate_id']] ?? null,
            ], $lines),
            self::QUANTITY_SCALE,
        );
        $computed = [];

        foreach ($lines as $index => $line) {
            $amount = $document['lines'][$index];
            $computed[] = [
                'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $amount['quantity'],
                'unit_price' => $amount['unit_price'],
                'vat_rate_id' => $line['vat_rate_id'] ?? null,
                'cost_type_id' => $line['cost_type_id'],
                'work_order_id' => $line['work_order_id'] ?? null,
                'net_amount' => $amount['net'],
                'vat_amount' => $amount['vat'],
                'total_amount' => $amount['total'],
                'sort_order' => $index,
            ];
        }

        return ['lines' => $computed, 'net' => $document['net'], 'vat' => $document['vat'], 'total' => $document['total']];
    }
}
