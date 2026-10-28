<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductSupplier>
 */
class ProductSupplierFactory extends Factory
{
    protected $model = ProductSupplier::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'supplier_id' => Registry::factory()->supplier(),
            'price' => fake()->randomFloat(2, 1, 500),
            'cost' => fake()->randomFloat(2, 1, 500),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
