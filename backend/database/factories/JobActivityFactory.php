<?php

namespace Database\Factories;

use App\Models\JobActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobActivity>
 */
class JobActivityFactory extends Factory
{
    protected $model = JobActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
