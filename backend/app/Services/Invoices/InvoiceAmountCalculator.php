<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\VatRate;
use App\Support\Amounts\LineAmountCalculator;

/**
 * Server-side amounts of an invoice (spec 0194, D-9; spec 0253): the lines go
 * through the shared engine with the VAT computed once per rate, so the header
 * totals are the exact sums of the lines and match the electronic invoice.
 */
final class InvoiceAmountCalculator
{
    private const int QUANTITY_SCALE = 2;

    public function __construct(private readonly LineAmountCalculator $engine) {}

    /**
     * @param  array<int, array<string, mixed>>  $lines  validated payload lines
     * @param  array<int, string>  $vatRates  vat_rate_id => rate (decimal string)
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     */
    public function compute(array $lines, array $vatRates): array
    {
        $lines = array_values($lines);
        $document = $this->engine->documentPerRate(
            array_map(fn (array $line): array => [
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'vat_rate' => $vatRates[$line['vat_rate_id']],
            ], $lines),
            self::QUANTITY_SCALE,
        );

        $computed = [];

        foreach ($lines as $index => $line) {
            $amounts = $document['lines'][$index];
            $computed[] = [
                'quote_line_id' => $line['quote_line_id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'description' => $line['description'],
                'quantity' => $amounts['quantity'],
                'unit_price' => $amounts['unit_price'],
                'vat_rate_id' => $line['vat_rate_id'],
                'net_amount' => $amounts['net'],
                'vat_amount' => $amounts['vat'],
                'total_amount' => $amounts['total'],
                'sort_order' => $index,
            ];
        }

        return ['lines' => $computed, 'net' => $document['net'], 'vat' => $document['vat'], 'total' => $document['total']];
    }

    /**
     * @param  array<int, int>  $vatRateIds
     * @return array<int, string>
     */
    public function vatRates(array $vatRateIds): array
    {
        return VatRate::query()->whereIn('id', array_unique($vatRateIds))->pluck('rate', 'id')
            ->map(fn (mixed $rate): string => $this->engine->normalize($rate))->all();
    }
}
