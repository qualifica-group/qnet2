<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\VatRate;
use App\Services\PurchaseRequests\PurchaseRequestAmountCalculator;

/**
 * Server-side amounts of a purchase order (spec 0210, D-12): the very same
 * formulas as the purchase requests (PurchaseRequestAmountCalculator, bcmath,
 * half-up to 2 decimals), plus the order-specific plumbing: the VAT rates of a
 * payload and the persistence of the header totals as the sums of the lines.
 */
final class PurchaseOrderAmountCalculator
{
    public function __construct(private readonly PurchaseRequestAmountCalculator $amounts) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  each with `vat_rate_id`
     * @return array<int, string> vat_rate_id => rate
     */
    public function vatRates(array $lines): array
    {
        $ids = array_filter(array_unique(array_column($lines, 'vat_rate_id')));

        return VatRate::query()->whereIn('id', $ids)->pluck('rate', 'id')
            ->map(fn (mixed $rate): string => (string) $rate)->all();
    }

    /**
     * @param  array<string, mixed>  $line  with `quantity`, `unit_price`, `vat_rate_id`
     * @param  array<int, string>  $rates  vat_rate_id => rate
     * @return array{quantity: string, unit_price: string, taxable_amount: string, vat_amount: string, total_amount: string}
     */
    public function line(array $line, array $rates): array
    {
        return $this->amounts->line($line['quantity'], $line['unit_price'], $rates[$line['vat_rate_id']] ?? null);
    }

    /**
     * Rewrite the header totals from the stored lines.
     */
    public function refreshTotals(PurchaseOrder $order): void
    {
        $order->forceFill($this->amounts->totals($order->lines()->get(['taxable_amount', 'vat_amount'])))->save();
    }
}
