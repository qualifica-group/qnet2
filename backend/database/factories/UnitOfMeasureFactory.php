<?php

namespace Database\Factories;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasure>
 */
class UnitOfMeasureFactory extends Factory
{
    protected $model = UnitOfMeasure::class;

    /** Incrementing counter backing `code`/`symbol`'s uniqueness. */
    private static int $nextSuffix = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = self::$nextSuffix++;

        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'unit_'.$suffix,
            'symbol' => 'u'.$suffix,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
