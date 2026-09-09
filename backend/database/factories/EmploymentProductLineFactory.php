<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\EmploymentProductLine;
use App\Models\EmploymentProfile;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentProductLine>
 */
class EmploymentProductLineFactory extends Factory
{
    protected $model = EmploymentProductLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_profile_id' => EmploymentProfile::factory(),
            'business_function_id' => BusinessFunction::factory(),
            'product_category_id' => ProductCategory::factory(),
        ];
    }
}
