<?php

namespace Database\Factories;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'parent_id' => null,
            'inherits_product_attributes' => true,
            'inherits_opportunity_attributes' => true,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    /** A category that opts out of inheriting its ancestors' attributes in BOTH usage contexts. */
    public function notInheriting(): static
    {
        return $this->state(fn (): array => [
            'inherits_product_attributes' => false,
            'inherits_opportunity_attributes' => false,
        ]);
    }

    /** A category that opts out of inheriting only $context's attributes, keeping the other context open. */
    public function notInheritingIn(AttributeContext $context): static
    {
        return $this->state(fn (): array => [$context->inheritanceColumn() => false]);
    }
}
