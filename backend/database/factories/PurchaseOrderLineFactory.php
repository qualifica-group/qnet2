<?php

namespace Database\Factories;

use App\Models\CostType;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    /**
     * Amounts are consistent with a 1.000 x 100.00 line without VAT.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'cost_type_id' => CostType::factory(),
            'quantity' => '1.000',
            'unit_price' => '100.00',
            'taxable_amount' => '100.00',
            'vat_amount' => '0.00',
            'total_amount' => '100.00',
        ];
    }
}
