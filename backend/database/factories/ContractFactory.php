<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    /**
     * Default: a bare contract row, every lifecycle date null except
     * `accepted_at` (D-7: always set once the contract exists) — tests opt
     * into `renewal_date`/`expiry_date`/suspension/termination via states as
     * needed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'contract_status_id' => ContractStatus::factory(),
            'accepted_at' => now()->toDateString(),
            'validated_at' => null,
            'validated_by' => null,
            'renewal_date' => null,
            'expiry_date' => null,
            'terminated_at' => null,
            'terminated_by' => null,
            'termination_reason' => null,
            'payment_notes' => null,
            'comments' => null,
            'suspended_at' => null,
            'status_before_suspension_id' => null,
        ];
    }
}
