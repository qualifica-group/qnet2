<?php

namespace Database\Factories;

use App\Models\AgentFiscalProfile;
use App\Models\CommissionSettlement;
use App\Models\Company;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSettlement>
 */
class CommissionSettlementFactory extends Factory
{
    protected $model = CommissionSettlement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commission_settlement_run_id' => null,
            'company_id' => Company::factory(),
            'referent_id' => Referent::factory(),
            // Same referent as the settlement: resolved after referent_id.
            'agent_fiscal_profile_id' => fn (array $attributes) => AgentFiscalProfile::factory()->create([
                'referent_id' => $attributes['referent_id'],
            ])->id,
            'status' => 'DRAFT',
            'number' => null,
            'year' => null,
            'period_from' => '2026-09-01',
            'period_to' => '2026-09-30',
            'accruals_amount' => '0.00',
            'adjustments_amount' => '0.00',
            'taxable_amount' => '0.00',
            'vat_rate' => null,
            'vat_amount' => '0.00',
            'withholding_enabled' => false,
            'withholding_rate' => null,
            'withholding_base_percent' => null,
            'withholding_amount' => '0.00',
            'enasarco_enabled' => false,
            'enasarco_mandate_type' => null,
            'enasarco_base_amount' => '0.00',
            'enasarco_agent_amount' => '0.00',
            'enasarco_principal_amount' => '0.00',
            'net_payable' => '0.00',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    public function issued(int $number = 1, int $year = 2026): static
    {
        return $this->state(fn (): array => [
            'status' => 'ISSUED',
            'number' => $number,
            'year' => $year,
            'issued_at' => now(),
            'issued_by' => User::factory(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
            'cancellation_reason' => 'Cancelled in test',
        ]);
    }
}
