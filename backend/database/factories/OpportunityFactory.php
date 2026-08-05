<?php

namespace Database\Factories;

use App\Models\Opportunity;
use App\Models\Registry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Opportunity>
 */
class OpportunityFactory extends Factory
{
    protected $model = Opportunity::class;

    /**
     * Default: the 2 mandatory columns (D-4); every other (optional) relation
     * stays null. Spec 0082: there is no status FK any more.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' deal',
            'registry_id' => Registry::factory(),
            'referent_id' => null,
            'commercial_id' => null,
            'reporter_id' => null,
            'supervisor_id' => null,
            'source_id' => null,
            'operational_site_id' => null,
            'lead_id' => null,
            'start_date' => fake()->optional()->date(),
            'estimated_value' => fake()->optional()->randomFloat(2, 500, 50000),
            'expected_close_date' => fake()->optional()->date(),
            'success_probability' => fake()->optional()->numberBetween(0, 100),
            'general_notes' => null,
        ];
    }
}
