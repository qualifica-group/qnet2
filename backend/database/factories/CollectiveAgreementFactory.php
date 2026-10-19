<?php

namespace Database\Factories;

use App\Models\CollectiveAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectiveAgreement>
 */
class CollectiveAgreementFactory extends Factory
{
    protected $model = CollectiveAgreement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'code' => fake()->optional()->bothify('CCNL-##'),
        ];
    }
}
