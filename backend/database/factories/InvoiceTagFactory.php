<?php

namespace Database\Factories;

use App\Models\InvoiceTag;
use App\Support\BadgeTokens;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceTag>
 */
class InvoiceTagFactory extends Factory
{
    protected $model = InvoiceTag::class;

    /** Incrementing counter backing `sort_order`. */
    private static int $nextSortOrder = 100;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'color' => fake()->randomElement(BadgeTokens::colors()),
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
        ];
    }
}
