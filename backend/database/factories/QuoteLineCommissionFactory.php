<?php

namespace Database\Factories;

use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\Referent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuoteLineCommission> */
class QuoteLineCommissionFactory extends Factory
{
    protected $model = QuoteLineCommission::class;

    public function definition(): array
    {
        return [
            'quote_line_id' => QuoteLine::factory(),
            'commission_configuration_id' => null,
            'recipient_role' => CommissionRecipientRole::Commercial,
            'recipient_type' => 'referent',
            'recipient_id' => Referent::factory(),
            'commission_type' => CommissionType::Percentage,
            'value' => 5,
            'calculated_amount' => 5,
            'internal_note' => null,
            'origin' => CommissionOrigin::ManualOverride,
        ];
    }
}
