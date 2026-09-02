<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectProductLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectProductLine>
 */
class ProjectProductLineFactory extends Factory
{
    protected $model = ProjectProductLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'product_category_id' => ProductCategory::factory(),
        ];
    }
}
