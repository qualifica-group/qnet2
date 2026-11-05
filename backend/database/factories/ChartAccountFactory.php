<?php

namespace Database\Factories;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use App\Models\ChartAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a cost group; use account() to build a child account.
 *
 * @extends Factory<ChartAccount>
 */
class ChartAccountFactory extends Factory
{
    protected $model = ChartAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'level' => ChartAccountLevel::Group,
            'parent_id' => null,
            'kind' => ChartAccountKind::Cost,
            'code' => 'T'.fake()->unique()->numerify('#####'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }

    public function revenue(): static
    {
        return $this->state(fn (): array => ['kind' => ChartAccountKind::Revenue]);
    }

    public function account(?ChartAccount $group = null): static
    {
        return $this->state(fn (): array => [
            'level' => ChartAccountLevel::Account,
            'kind' => null,
            'parent_id' => ($group ?? ChartAccount::factory()->create())->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
