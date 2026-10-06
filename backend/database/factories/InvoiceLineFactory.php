<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    protected $model = InvoiceLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 10);
        $unitPrice = fake()->randomFloat(2, 10, 500);
        $net = round($quantity * $unitPrice, 2);
        $vat = round($net * 0.22, 2);

        return [
            'invoice_id' => Invoice::factory(),
            'quote_line_id' => null,
            'product_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate_id' => VatRate::factory(),
            'net_amount' => $net,
            'vat_amount' => $vat,
            'total_amount' => round($net + $vat, 2),
            'sort_order' => 0,
        ];
    }
}
