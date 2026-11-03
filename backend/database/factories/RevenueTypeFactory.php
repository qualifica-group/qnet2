<?php

namespace Database\Factories;

use App\Models\ChartAccount;
use App\Models\RevenueType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RevenueType>
 */
class RevenueTypeFactory extends Factory
{
    protected $model = RevenueType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chart_account_id' => fn (): int => ChartAccount::factory()->revenue()->create()->id,
            'code' => 'R'.fake()->unique()->numerify('#####'),
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
