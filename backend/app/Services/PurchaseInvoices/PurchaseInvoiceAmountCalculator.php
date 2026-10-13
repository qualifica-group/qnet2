<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Services\PurchaseOrders\PurchaseOrderAmountCalculator;
use App\Services\PurchaseRequests\PurchaseRequestAmountCalculator;

/**
 * Server-side amounts of a payable document (spec 0250, D-5): the line formulas
 * of the purchase orders (bcmath, 3-decimal quantity, null VAT rate = 0) and a
 * header that is the exact sum of the rounded lines.
 */
final class PurchaseInvoiceAmountCalculator
{
    public function __construct(
        private readonly PurchaseOrderAmountCalculator $orderAmounts,
        private readonly PurchaseRequestAmountCalculator $requestAmounts,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  validated payload lines
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     */
    public function compute(array $lines): array
    {
        $lines = array_map(fn (array $line): array => $line + ['vat_rate_id' => null], array_values($lines));
        $rates = $this->orderAmounts->vatRates($lines);
        $computed = [];
        $amounts = [];

        foreach ($lines as $index => $line) {
            $amount = $this->orderAmounts->line($line, $rates);
            $amounts[] = $amount;
            $computed[] = [
                'purchase_order_line_id' => $line['purchase_order_line_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $amount['quantity'],
                'unit_price' => $amount['unit_price'],
                'vat_rate_id' => $line['vat_rate_id'] ?? null,
                'cost_type_id' => $line['cost_type_id'],
                'work_order_id' => $line['work_order_id'] ?? null,
                'net_amount' => $amount['taxable_amount'],
                'vat_amount' => $amount['vat_amount'],
                'total_amount' => $amount['total_amount'],
                'sort_order' => $index,
            ];
        }

        $totals = $this->requestAmounts->totals($amounts);

        return ['lines' => $computed, 'net' => $totals['taxable_total'], 'vat' => $totals['vat_total'], 'total' => $totals['grand_total']];
    }
}
