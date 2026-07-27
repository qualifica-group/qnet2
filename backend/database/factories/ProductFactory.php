<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'cost' => fake()->randomFloat(2, 1, 500),
            'price' => fake()->randomFloat(2, 1, 1000),
            'category_id' => ProductCategory::factory(),
            'product_type' => ProductType::Service,
            'vat_rate_id' => null,
            'supplier_id' => null,
            'state_id' => null,
        ];
    }
}
