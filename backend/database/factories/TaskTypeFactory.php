<?php

namespace Database\Factories;

use App\Models\TaskType;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskType>
 */
class TaskTypeFactory extends Factory
{
    protected $model = TaskType::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 0;

    /**
     * A plain task type row (spec 0101, D-4): no system_key, no numeric
     * weight — every row is custom. `color` is drawn from the shared
     * palette allow-list, never a hex value; `icon` stays null so a test
     * asserting the badge's icon has to set it explicitly.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(BadgeTokens::colors()),
            'icon' => null,
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
        ];
    }
}
