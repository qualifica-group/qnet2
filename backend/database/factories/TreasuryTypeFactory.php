<?php

namespace Database\Factories;

use App\Enums\ChartAccountKind;
use App\Models\ChartAccount;
use App\Models\TreasuryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreasuryType>
 */
class TreasuryTypeFactory extends Factory
{
    protected $model = TreasuryType::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chart_account_id' => fn (): int => ChartAccount::factory()
                ->account(ChartAccount::factory()->state(['kind' => ChartAccountKind::Financial])->create())
                ->create()->id,
            'code' => 'F'.fake()->unique()->numerify('#####'),
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
