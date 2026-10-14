<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrder;
use App\Models\VatRate;
use App\Support\Amounts\LineAmountCalculator;

/**
 * Server-side amounts of a purchase order (spec 0210, D-12; spec 0253): the
 * shared engine with the VAT per line (not a fiscal document), plus the
 * order-specific plumbing: the VAT rates of a payload, the mapping to the
 * `taxable_amount` column and the persistence of the header totals as the
 * sums of the lines.
 */
final class PurchaseOrderAmountCalculator
{
    public const int QUANTITY_SCALE = 3;

    public function __construct(private readonly LineAmountCalculator $engine) {}

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
        $amounts = $this->engine->line($line['quantity'], $line['unit_price'], $rates[$line['vat_rate_id']] ?? null, self::QUANTITY_SCALE);

        return [
            'quantity' => $amounts['quantity'],
            'unit_price' => $amounts['unit_price'],
            'taxable_amount' => $amounts['net'],
            'vat_amount' => $amounts['vat'],
            'total_amount' => $amounts['total'],
        ];
    }

    /**
     * Rewrite the header totals from the stored lines.
     */
    public function refreshTotals(PurchaseOrder $order): void
    {
        $totals = $this->engine->sum(
            $order->lines()->get(['taxable_amount', 'vat_amount'])
                ->map(fn ($line): array => ['net' => $line->taxable_amount, 'vat' => $line->vat_amount]),
        );

        $order->forceFill(['taxable_total' => $totals['net'], 'vat_total' => $totals['vat'], 'grand_total' => $totals['total']])->save();
    }
}
