<?php

namespace Database\Factories;

use App\Models\BusinessFunction;
use App\Models\Country;
use App\Models\PipelineStatus;
use App\Models\ProductCategory;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * `country_id` is REQUIRED at the FormRequest layer (BR-4, spec 0027), so
     * the default fixture always carries one; `state_id`/`province_id`/
     * `city_id` stay unset here (as before) since they were never part of the
     * default fixture and remain optional.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->paragraph(),
            'pipeline_status_id' => PipelineStatus::factory(),
            'country_id' => Country::factory(),
            'start_date' => null,
            'end_date' => null,
            'total_budget' => fake()->optional()->randomFloat(2, 1000, 500000),
            'target_lead' => fake()->optional()->numberBetween(1, 200),
        ];
    }

    /**
     * `code` (BR-1: PRJ-0001...) is service-generated in production and
     * deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Project $project): void {
            $project->code ??= sprintf('PRJ-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }

    /**
     * Spec 0094, D-1/D-2: the default fixture carries NO product line (like
     * OpportunityFactory's own default), since `business_function_id`/
     * `product_category_id` were never part of the plain `definition()`
     * either. Tests that need a coherent classification row opt in here — one
     * row, business function and category paired coherently (the category
     * created UNDER the function, matching ProductLineSetValidator's
     * cross-row rule).
     */
    public function withProductLine(): static
    {
        return $this->afterCreating(function (Project $project): void {
            $businessFunction = BusinessFunction::factory()->create();

            $project->productLines()->create([
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]);
        });
    }
}
