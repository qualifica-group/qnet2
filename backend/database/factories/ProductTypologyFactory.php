<?php

namespace Database\Factories;

use App\Models\ProductTypology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTypology>
 */
class ProductTypologyFactory extends Factory
{
    protected $model = ProductTypology::class;

    /** Incrementing counter backing `code`'s uniqueness. */
    private static int $nextSuffix = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = self::$nextSuffix++;

        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'typology_'.$suffix,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
