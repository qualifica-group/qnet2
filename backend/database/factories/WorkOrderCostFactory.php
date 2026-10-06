<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderCost>
 */
class WorkOrderCostFactory extends Factory
{
    protected $model = WorkOrderCost::class;

    /**
     * Coherent, already-rounded amounts (no VAT rate), like QuoteLineFactory.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 20);
        $unitPrice = fake()->randomFloat(2, 1, 500);
        $netAmount = round($quantity * $unitPrice, 2);

        return [
            'work_order_id' => WorkOrder::factory(),
            'product_id' => Product::factory()->costOnly(),
            'quote_line_id' => null,
            'quantity' => $quantity,
            'unit_of_measure_id' => null,
            'unit_price' => $unitPrice,
            'vat_rate_id' => null,
            'net_amount' => $netAmount,
            'vat_amount' => 0,
            'total_amount' => $netAmount,
            'incurred_on' => fake()->date(),
            'supplier_id' => null,
            'document_reference' => null,
            'additional_description' => null,
            'sort_order' => 0,
        ];
    }
}
