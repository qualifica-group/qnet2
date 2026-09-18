<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
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
            // Both usages (spec 0142): the pre-0142 behaviour, where any
            // product fit either Offerta tab. The model's own default
            // (Sellable only) is exercised through saleOnly()/the Service.
            'usages' => [ProductUsage::Sale, ProductUsage::Cost],
            'vat_rate_id' => null,
            'supplier_id' => null,
            'unit_of_measure_id' => UnitOfMeasure::factory(),
            'product_typology_id' => ProductTypology::factory(),
        ];
    }

    public function saleOnly(): static
    {
        return $this->state(['usages' => [ProductUsage::Sale]]);
    }

    public function costOnly(): static
    {
        return $this->state(['usages' => [ProductUsage::Cost]]);
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
