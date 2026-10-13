<?php

namespace Database\Factories;

use App\Models\PurchaseOrderStatusLabel;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderStatusLabel>
 */
class PurchaseOrderStatusLabelFactory extends Factory
{
    protected $model = PurchaseOrderStatusLabel::class;

    /**
     * The seven rows already exist after the migration, so tests normally read
     * them; the factory only serves a test that needs an extra row (factories
     * run unguarded, so the non-fillable `system_key` can be set here).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'system_key' => fake()->unique()->lexify('key_????????'),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->randomElement(BadgeTokens::colors()),
            'icon' => null,
            'sort_order' => 0,
        ];
    }
}
