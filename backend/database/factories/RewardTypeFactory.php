<?php

namespace Database\Factories;

use App\Models\RewardType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardType>
 */
class RewardTypeFactory extends Factory
{
    protected $model = RewardType::class;

    /**
     * The 14 badge color tokens shared by the color-token picker
     * (frontend/src/features/custom-fields/badge-color-tokens.ts) — `color`
     * is a palette TOKEN, never a hex value.
     *
     * @var array<int, string>
     */
    private const array COLOR_TOKENS = [
        'slate', 'gray', 'red', 'orange', 'amber', 'yellow', 'green',
        'emerald', 'teal', 'blue', 'indigo', 'violet', 'purple', 'pink',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(self::COLOR_TOKENS),
        ];
    }
}
