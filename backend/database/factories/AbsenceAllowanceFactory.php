<?php

namespace Database\Factories;

use App\Models\AbsenceAllowance;
use App\Models\AbsenceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbsenceAllowance>
 */
class AbsenceAllowanceFactory extends Factory
{
    protected $model = AbsenceAllowance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'absence_type_id' => AbsenceType::factory(),
            'year' => (int) now()->format('Y'),
            'annual_minutes' => 9600,
            'carried_over_minutes' => 0,
        ];
    }
}
