<?php

declare(strict_types=1);

namespace App\Services\PurchaseRequests;

/**
 * Server-side amounts of a purchase request (spec 0208, D-12), with bcmath and
 * half-up rounding to 2 decimals: taxable = qty * unit price, vat = taxable *
 * rate / 100, total = taxable + vat. The header totals are the exact sums of
 * the rounded lines. The client only previews these figures.
 */
final class PurchaseRequestAmountCalculator
{
    private const int MONEY_SCALE = 2;

    private const int QUANTITY_SCALE = 3;

    private const int WORKING_SCALE = 8;

    /**
     * @param  string|float|int  $quantity
     * @param  string|float|int  $unitPrice
     * @return array{quantity: string, unit_price: string, taxable_amount: string, vat_amount: string, total_amount: string}
     */
    public function line(mixed $quantity, mixed $unitPrice, ?string $vatRate): array
    {
        $quantity = $this->round($this->normalize($quantity), self::QUANTITY_SCALE);
        $unitPrice = $this->round($this->normalize($unitPrice), self::MONEY_SCALE);
        $taxable = $this->round(bcmul($quantity, $unitPrice, self::WORKING_SCALE), self::MONEY_SCALE);
        $vat = $vatRate === null
            ? '0.00'
            : $this->round(bcdiv(bcmul($taxable, $this->normalize($vatRate), self::WORKING_SCALE), '100', self::WORKING_SCALE), self::MONEY_SCALE);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'taxable_amount' => $taxable,
            'vat_amount' => $vat,
            'total_amount' => bcadd($taxable, $vat, self::MONEY_SCALE),
        ];
    }

    /**
     * @param  iterable<array{taxable_amount: string, vat_amount: string}>  $lines  already computed lines
     * @return array{taxable_total: string, vat_total: string, grand_total: string}
     */
    public function totals(iterable $lines): array
    {
        $taxable = $vat = '0.00';

        foreach ($lines as $line) {
            $taxable = bcadd($taxable, (string) $line['taxable_amount'], self::MONEY_SCALE);
            $vat = bcadd($vat, (string) $line['vat_amount'], self::MONEY_SCALE);
        }

        return [
            'taxable_total' => $taxable,
            'vat_total' => $vat,
            'grand_total' => bcadd($taxable, $vat, self::MONEY_SCALE),
        ];
    }

    /** A plain decimal string bcmath accepts (JSON numbers may arrive as floats). */
    private function normalize(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text : sprintf('%.8F', (float) $value);
    }

    private function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, bccomp($value, '0', self::WORKING_SCALE) < 0 ? '-'.$half : $half, $scale);
    }
}
