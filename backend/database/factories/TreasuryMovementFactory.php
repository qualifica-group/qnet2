<?php

namespace Database\Factories;

use App\Enums\TreasuryMovementDirection;
use App\Enums\TreasuryMovementType;
use App\Models\CostType;
use App\Models\FinancialAccount;
use App\Models\RevenueType;
use App\Models\TreasuryMovement;
use App\Models\TreasuryType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to an income with a revenue sub-account; use the type states for the others.
 *
 * @extends Factory<TreasuryMovement>
 */
class TreasuryMovementFactory extends Factory
{
    protected $model = TreasuryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => TreasuryMovementType::Income,
            'direction' => null,
            'movement_date' => fake()->dateTimeBetween('2026-01-01', '2026-10-31')->format('Y-m-d'),
            'financial_account_id' => FinancialAccount::factory(),
            'counterpart_account_id' => null,
            'amount' => fake()->randomFloat(2, 1, 5000),
            'description' => fake()->sentence(4),
            'cost_type_id' => null,
            'revenue_type_id' => RevenueType::factory(),
            'treasury_type_id' => null,
        ];
    }

    public function income(): static
    {
        return $this->state(fn (): array => [
            'type' => TreasuryMovementType::Income,
            'revenue_type_id' => RevenueType::factory(),
            'cost_type_id' => null,
            'treasury_type_id' => null,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'type' => TreasuryMovementType::Expense,
            'cost_type_id' => CostType::factory(),
            'revenue_type_id' => null,
            'treasury_type_id' => null,
        ]);
    }

    /** Transfer between two accounts of the same company (D-6): the destination copies the source company. */
    public function transfer(?FinancialAccount $from = null, ?FinancialAccount $to = null): static
    {
        $from ??= FinancialAccount::factory()->create();
        $to ??= FinancialAccount::factory()->create(['company_id' => $from->company_id]);

        return $this->state(fn (): array => [
            'type' => TreasuryMovementType::Transfer,
            'financial_account_id' => $from->id,
            'counterpart_account_id' => $to->id,
            'treasury_type_id' => TreasuryType::factory(),
            'cost_type_id' => null,
            'revenue_type_id' => null,
        ]);
    }

    public function adjustment(TreasuryMovementDirection $direction = TreasuryMovementDirection::In): static
    {
        return $this->state(fn (): array => [
            'type' => TreasuryMovementType::Adjustment,
            'direction' => $direction,
            'treasury_type_id' => TreasuryType::factory(),
            'cost_type_id' => null,
            'revenue_type_id' => null,
        ]);
    }
}
