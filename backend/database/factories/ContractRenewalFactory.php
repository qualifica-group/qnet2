<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractRenewal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContractRenewal>
 */
class ContractRenewalFactory extends Factory
{
    protected $model = ContractRenewal::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'number' => 1,
            'starts_on' => '2027-01-01',
            'previous_renewal_date' => null,
            'previous_expiry_date' => '2026-12-31',
            'renewal_date' => null,
            'expiry_date' => '2027-12-31',
            'renewed_by' => null,
        ];
    }
}
