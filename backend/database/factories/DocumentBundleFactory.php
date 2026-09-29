<?php

namespace Database\Factories;

use App\Models\DocumentBundle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentBundle>
 */
class DocumentBundleFactory extends Factory
{
    protected $model = DocumentBundle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
