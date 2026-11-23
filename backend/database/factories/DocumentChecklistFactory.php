<?php

namespace Database\Factories;

use App\Models\DocumentChecklist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChecklist>
 */
class DocumentChecklistFactory extends Factory
{
    protected $model = DocumentChecklist::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'code' => strtoupper(fake()->unique()->bothify('CHK-####??')),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
