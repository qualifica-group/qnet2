<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\PayrollCost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Synthetic personnel costs for tests only: never real payroll data.
 *
 * @extends Factory<PayrollCost>
 */
class PayrollCostFactory extends Factory
{
    protected $model = PayrollCost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'user_id' => User::factory(),
            'period' => '2026-10',
            'tax_code' => strtoupper(fake()->unique()->bothify('??????##?##?###?')),
            'salary_cost' => '1500.00',
            'contribution_cost' => '450.00',
            'insurance_cost' => '6.00',
            'total_cost' => '1956.00',
            'ordinary_hours' => '168.00',
            'hired_on' => null,
            'terminated_on' => null,
            'imported_by' => null,
        ];
    }
}
