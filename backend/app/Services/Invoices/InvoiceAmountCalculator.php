<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\VatRate;

/**
 * Server-side amounts of an invoice (spec 0194, D-9): every line is recomputed
 * with bcmath and rounded half away from zero to 2 decimals; the header totals
 * are the exact sums of the rounded lines (no float accumulation).
 */
final class InvoiceAmountCalculator
{
    private const int SCALE = 2;

    /**
     * @param  array<int, array<string, mixed>>  $lines  validated payload lines
     * @param  array<int, string>  $vatRates  vat_rate_id => rate (decimal string)
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     */
    public function compute(array $lines, array $vatRates): array
    {
        $computed = [];
        $net = $vat = '0.00';

        foreach (array_values($lines) as $index => $line) {
            $quantity = $this->round($this->normalize($line['quantity']));
            $price = $this->round($this->normalize($line['unit_price']));
            $lineNet = $this->round(bcmul($quantity, $price, 4));
            $lineVat = $this->round(bcdiv(bcmul($lineNet, $vatRates[$line['vat_rate_id']], 4), '100', 6));

            $computed[] = [
                'quote_line_id' => $line['quote_line_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $quantity,
                'unit_price' => $price,
                'vat_rate_id' => $line['vat_rate_id'],
                'net_amount' => $lineNet,
                'vat_amount' => $lineVat,
                'total_amount' => bcadd($lineNet, $lineVat, self::SCALE),
                'sort_order' => $index,
            ];
            $net = bcadd($net, $lineNet, self::SCALE);
            $vat = bcadd($vat, $lineVat, self::SCALE);
        }

        return ['lines' => $computed, 'net' => $net, 'vat' => $vat, 'total' => bcadd($net, $vat, self::SCALE)];
    }

    /**
     * @param  array<int, int>  $vatRateIds
     * @return array<int, string>
     */
    public function vatRates(array $vatRateIds): array
    {
        return VatRate::query()->whereIn('id', array_unique($vatRateIds))->pluck('rate', 'id')
            ->map(fn (mixed $rate): string => $this->normalize($rate))->all();
    }

    /** A plain decimal string bcmath accepts (JSON numbers arrive as floats, possibly in exponent form). */
    public function normalize(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text : sprintf('%.6F', (float) $value);
    }

    private function round(string $value): string
    {
        $half = bccomp($value, '0', 6) < 0 ? '-0.005' : '0.005';

        return bcadd($value, $half, self::SCALE);
    }
}
