<?php

namespace Database\Factories;

use App\Enums\QuoteLineType;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuoteLine>
 */
class QuoteLineFactory extends Factory
{
    protected $model = QuoteLine::class;

    /**
     * Default: a REVENUE line with coherent, already-rounded amounts (D-12:
     * `net_amount` = quantity * unit_price, no VAT rate, `vat_amount` 0,
     * `total_amount` = `net_amount` — mirrors the D-31 no-VAT-rate case),
     * matching the persisted-and-frozen shape the model never recomputes.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 1, 500);
        $netAmount = round($quantity * $unitPrice, 2);

        return [
            'quote_id' => Quote::factory(),
            'line_type' => QuoteLineType::Revenue,
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate_id' => null,
            'net_amount' => $netAmount,
            'vat_amount' => 0,
            'total_amount' => $netAmount,
            'sort_order' => 0,
        ];
    }

    public function cost(): static
    {
        return $this->state(fn () => ['line_type' => QuoteLineType::Cost]);
    }
}
