<?php

namespace Database\Factories;

use App\Models\CommissionSettlementRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSettlementRun>
 */
class CommissionSettlementRunFactory extends Factory
{
    protected $model = CommissionSettlementRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'filters' => null,
            'min_amount' => '0.00',
            'settlements_count' => 0,
            'skipped' => [],
            'created_by' => User::factory(),
        ];
    }
}
