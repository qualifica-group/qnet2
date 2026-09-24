<?php

namespace Database\Factories;

use App\Models\TaskImportance;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskImportance>
 */
class TaskImportanceFactory extends Factory
{
    protected $model = TaskImportance::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 0;

    /**
     * A plain task importance row (spec 0101, D-4): no system_key, no numeric
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
            'is_default' => false,
        ];
    }

    /**
     * The row a new Task falls back to when the field is omitted (spec
     * 0154, D-8). Also forces `is_active` true: an inactive row cannot be
     * the default (a default must stay selectable).
     */
    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true, 'is_active' => true]);
    }
}
