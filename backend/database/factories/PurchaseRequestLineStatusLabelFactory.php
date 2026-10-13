<?php

namespace Database\Factories;

use App\Models\PurchaseRequestLineStatusLabel;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestLineStatusLabel>
 */
class PurchaseRequestLineStatusLabelFactory extends Factory
{
    protected $model = PurchaseRequestLineStatusLabel::class;

    private static int $nextSortOrder = 1000;

    /**
     * The real rows are created by the migration (one per enum case): a factory
     * row is only for tests that need an extra one, hence the throwaway
     * `system_key` (the factory may set it although it is not mass-assignable).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_key' => fake()->unique()->lexify('test_????????'),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(BadgeTokens::colors()),
            'icon' => null,
            'sort_order' => self::$nextSortOrder++,
        ];
    }
}
