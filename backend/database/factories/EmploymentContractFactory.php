<?php

namespace Database\Factories;

use App\Enums\RelationshipTypeEnum;
use App\Enums\SalaryPeriodEnum;
use App\Models\EmploymentContract;
use App\Models\EmploymentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmploymentContract>
 */
class EmploymentContractFactory extends Factory
{
    protected $model = EmploymentContract::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employment_profile_id' => EmploymentProfile::factory(),
            'starts_on' => fake()->unique()->dateTimeBetween('-10 years', '-1 day')->format('Y-m-d'),
            'relationship_type' => RelationshipTypeEnum::Employee->value,
            'company_id' => null,
            'collective_agreement_level_id' => null,
            'salary_amount' => null,
            'salary_period' => null,
            'change_reason' => null,
        ];
    }

    public function withSalary(string $amount = '2500.00', SalaryPeriodEnum $period = SalaryPeriodEnum::Monthly): static
    {
        return $this->state(fn (): array => [
            'salary_amount' => $amount,
            'salary_period' => $period->value,
        ]);
    }
}
