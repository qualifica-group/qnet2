<?php

namespace Database\Factories;

use App\Models\CollectiveAgreement;
use App\Models\CollectiveAgreementLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollectiveAgreementLevel>
 */
class CollectiveAgreementLevelFactory extends Factory
{
    protected $model = CollectiveAgreementLevel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collective_agreement_id' => CollectiveAgreement::factory(),
            'code' => fake()->unique()->bothify('L##??'),
            'description' => fake()->optional()->sentence(3),
            'sort_order' => 0,
        ];
    }
}
