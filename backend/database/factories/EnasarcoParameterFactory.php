<?php

namespace Database\Factories;

use App\Models\EnasarcoParameter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Demo values only: real rates and ceilings are entered by the user.
 *
 * @extends Factory<EnasarcoParameter>
 */
class EnasarcoParameterFactory extends Factory
{
    protected $model = EnasarcoParameter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'year' => 2000 + fake()->unique()->numberBetween(0, 100),
            'total_rate' => '17.00',
            'agent_share_percent' => '50.00',
            'single_firm_ceiling' => '45000.00',
            'multi_firm_ceiling' => '26000.00',
            'notes' => null,
        ];
    }
}
