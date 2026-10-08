<?php

namespace Database\Factories;

use App\Models\WorkOrderPaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPaymentStatus>
 */
class WorkOrderPaymentStatusFactory extends Factory
{
    protected $model = WorkOrderPaymentStatus::class;

    /** Incrementing counter backing `sort_order`. */
    private static int $nextSortOrder = 10;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->randomElement(['green', 'yellow', 'orange', 'blue', 'red']),
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
            'allows_delivery' => false,
        ];
    }

    public function deliverable(): static
    {
        return $this->state(['allows_delivery' => true]);
    }
}
