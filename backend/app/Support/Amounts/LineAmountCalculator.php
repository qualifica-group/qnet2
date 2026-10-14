<?php

declare(strict_types=1);

namespace App\Support\Amounts;

/**
 * The single bcmath engine for document and cost amounts (spec 0253, ADR 0018):
 * every figure is rounded half away from zero. A line rounds its quantity to
 * the document's scale and its unit price to cents, net = quantity x price
 * rounded to cents. Fiscal documents (invoices) compute the VAT once per rate
 * on the summed net, as the electronic invoice does, and split it over the
 * lines; purchase requests/orders and costs keep the VAT per line.
 */
final class LineAmountCalculator
{
    private const int MONEY_SCALE = 2;

    private const int WORKING_SCALE = 8;

    private const string ZERO = '0.00';

    /**
     * @return array{quantity: string, unit_price: string, net: string, vat: string, total: string}
     */
    public function line(mixed $quantity, mixed $unitPrice, ?string $vatRate, int $quantityScale): array
    {
        $quantity = $this->round($this->normalize($quantity), $quantityScale);
        $unitPrice = $this->round($this->normalize($unitPrice), self::MONEY_SCALE);
        $net = $this->round(bcmul($quantity, $unitPrice, self::WORKING_SCALE), self::MONEY_SCALE);
        $vat = $this->vatOf($net, $vatRate);

        return [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'net' => $net,
            'vat' => $vat,
            'total' => bcadd($net, $vat, self::MONEY_SCALE),
        ];
    }

    /**
     * @param  array<int, array{quantity: mixed, unit_price: mixed, vat_rate: ?string}>  $lines
     * @return array{lines: array<int, array{quantity: string, unit_price: string, net: string, vat: string, total: string}>, net: string, vat: string, total: string}
     */
    public function documentPerRate(array $lines, int $quantityScale): array
    {
        // Step 1: every line on its own, grouped by rate
        $computed = [];
        $groups = [];

        foreach (array_values($lines) as $index => $line) {
            $computed[$index] = $this->line($line['quantity'], $line['unit_price'], $line['vat_rate'], $quantityScale);
            $groups[$this->rateKey($line['vat_rate'])][] = $index;
        }

        // Step 2: the VAT of each rate is computed once on the summed net and split over its lines
        foreach ($groups as $indexes) {
            $rate = $lines[$indexes[0]]['vat_rate'];
            $computed = $this->allocate($computed, $indexes, $rate);
        }

        return ['lines' => $computed] + $this->sum($computed);
    }

    /**
     * Header totals as the exact sums of lines that already carry `net` and `vat`.
     *
     * @param  iterable<array{net: mixed, vat: mixed}>  $lines
     * @return array{net: string, vat: string, total: string}
     */
    public function sum(iterable $lines): array
    {
        $net = $vat = self::ZERO;

        foreach ($lines as $line) {
            $net = bcadd($net, (string) $line['net'], self::MONEY_SCALE);
            $vat = bcadd($vat, (string) $line['vat'], self::MONEY_SCALE);
        }

        return ['net' => $net, 'vat' => $vat, 'total' => bcadd($net, $vat, self::MONEY_SCALE)];
    }

    /** Cents of an amount typed by the user (half away from zero). */
    public function roundToCents(mixed $amount): string
    {
        return $this->round($this->normalize($amount), self::MONEY_SCALE);
    }

    /** Whether a payment/collection (rounded to cents) covers less than the installment amount. */
    public function isPartial(mixed $paid, mixed $amount): bool
    {
        return bccomp($this->roundToCents($paid), $this->roundToCents($amount), self::MONEY_SCALE) < 0;
    }

    /** A plain decimal string bcmath accepts (JSON numbers arrive as floats, possibly in exponent form). */
    public function normalize(mixed $value): string
    {
        $text = is_string($value) ? trim($value) : '';

        return preg_match('/^-?\d+(\.\d+)?$/', $text) === 1 ? $text : sprintf('%.'.self::WORKING_SCALE.'F', (float) $value);
    }

    /**
     * @param  array<int, array<string, string>>  $computed
     * @param  array<int, int>  $indexes  lines of one rate, in document order
     * @return array<int, array<string, string>>
     */
    private function allocate(array $computed, array $indexes, ?string $rate): array
    {
        $groupNet = self::ZERO;
        $lineVats = self::ZERO;
        $target = $indexes[0];

        foreach ($indexes as $index) {
            $groupNet = bcadd($groupNet, $computed[$index]['net'], self::MONEY_SCALE);
            $lineVats = bcadd($lineVats, $computed[$index]['vat'], self::MONEY_SCALE);

            if (bccomp($computed[$index]['net'], $computed[$target]['net'], self::MONEY_SCALE) > 0) {
                $target = $index;
            }
        }

        $difference = bcsub($this->vatOf($groupNet, $rate), $lineVats, self::MONEY_SCALE);
        $computed[$target]['vat'] = bcadd($computed[$target]['vat'], $difference, self::MONEY_SCALE);
        $computed[$target]['total'] = bcadd($computed[$target]['net'], $computed[$target]['vat'], self::MONEY_SCALE);

        return $computed;
    }

    private function vatOf(string $net, ?string $rate): string
    {
        if ($rate === null) {
            return self::ZERO;
        }

        $vat = bcdiv(bcmul($net, $this->normalize($rate), self::WORKING_SCALE), '100', self::WORKING_SCALE);

        return $this->round($vat, self::MONEY_SCALE);
    }

    private function rateKey(?string $rate): string
    {
        return $rate === null ? 'none' : bcadd($this->normalize($rate), '0', 4);
    }

    private function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, bccomp($value, '0', self::WORKING_SCALE) < 0 ? '-'.$half : $half, $scale);
    }
}
