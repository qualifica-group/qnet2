<?php

namespace Database\Factories;

use App\Models\CostType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoiceLine>
 */
class PurchaseInvoiceLineFactory extends Factory
{
    protected $model = PurchaseInvoiceLine::class;

    /**
     * Amounts are consistent with a 1.000 x 100.00 line without VAT.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_invoice_id' => PurchaseInvoice::factory(),
            'description' => fake()->sentence(3),
            'quantity' => '1.000',
            'unit_price' => '100.00',
            'cost_type_id' => CostType::factory(),
            'net_amount' => '100.00',
            'vat_amount' => '0.00',
            'total_amount' => '100.00',
            'sort_order' => 0,
        ];
    }
}
