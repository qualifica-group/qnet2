<?php

namespace Database\Factories;

use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    /** Incrementing counter backing `sort_order`, reset per factory instance. */
    private static int $nextSortOrder = 10;

    /** Incrementing counter backing `code`'s uniqueness (regex: ^[a-z][a-z0-9_]*$). */
    private static int $nextCodeSuffix = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'method_'.self::$nextCodeSuffix++,
            'payment_method_code' => fake()->optional()->numerify('MP##'),
            'description' => fake()->optional()->sentence(),
            'payment_instructions' => fake()->optional()->paragraph(),
            'payment_days' => fake()->optional()->numberBetween(0, 90),
            'sort_order' => self::$nextSortOrder++,
            'is_active' => true,
        ];
    }
}
