<?php

namespace Database\Factories;

use App\Enums\RewardStatusGroup;
use App\Models\RewardStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RewardStatus>
 */
class RewardStatusFactory extends Factory
{
    protected $model = RewardStatus::class;

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

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 10;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'color' => fake()->randomElement(self::COLOR_TOKENS),
            // A custom status defaults to the pending phase — the same mapping
            // the migration applies to pre-existing rows now that `open` is
            // gone from App\Enums\RewardStatusGroup.
            'group' => RewardStatusGroup::Pending,
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
        ];
    }
}
