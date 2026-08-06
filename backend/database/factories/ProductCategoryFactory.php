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
            'inherits_quote_attributes' => true,
            // Mirrors the column default. A factory-built tree bypasses
            // RequiresQuoteInheritance, so a test that needs a quoted BRANCH
            // must set the flag on every node it builds, exactly as the
            // service would have.
            'requires_quote' => false,
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    /** A category that opts out of inheriting its ancestors' attributes in EVERY usage context. */
    public function notInheriting(): static
    {
        return $this->state(fn (): array => [
            'inherits_product_attributes' => false,
            'inherits_opportunity_attributes' => false,
            'inherits_quote_attributes' => false,
        ]);
    }

    /** A category that opts out of inheriting only $context's attributes, keeping the other context open. */
    public function notInheritingIn(AttributeContext $context): static
    {
        return $this->state(fn (): array => [$context->inheritanceColumn() => false]);
    }
}
