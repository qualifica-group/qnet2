<?php

namespace Database\Factories;

use App\Models\DocumentChecklistCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentChecklistCategory>
 */
class DocumentChecklistCategoryFactory extends Factory
{
    protected $model = DocumentChecklistCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => 0,
        ];
    }
}
