<?php

namespace Database\Factories;

use App\Models\CommissionAccrual;
use App\Models\Referent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionAccrual>
 */
class CommissionAccrualFactory extends Factory
{
    protected $model = CommissionAccrual::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => null,
            'quote_line_id' => null,
            'quote_line_commission_id' => null,
            'invoice_installment_id' => null,
            'recipient_role' => 'COMMERCIAL',
            'recipient_type' => 'referent',
            'recipient_id' => Referent::factory(),
            'type' => 'ACCRUAL',
            'base_amount' => '100.00',
            'amount' => '50.00',
            'accrued_on' => now()->toDateString(),
        ];
    }
}
