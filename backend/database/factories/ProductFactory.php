<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
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
            'unit_of_measure_id' => UnitOfMeasure::factory(),
        ];
    }

    /**
     * `code` (spec 0065, D-1: PRD-0001...) is service-generated in production
     * and deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array —
     * mirrors ProjectFactory/CampaignFactory. `??=` lets an explicit
     * `code` override (e.g. `Product::factory()->create(['code' => 'X'])`,
     * which the factory's `Model::unguarded()` already assigned) win.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            $product->code ??= sprintf('PRD-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }
}
