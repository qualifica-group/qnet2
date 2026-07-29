<?php

namespace Database\Factories;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommissionConfiguration> */
class CommissionConfigurationFactory extends Factory
{
    protected $model = CommissionConfiguration::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'recipient_role' => CommissionRecipientRole::Commercial,
            'application_scope' => CommissionApplicationScope::Product,
            'product_category_id' => null,
            'product_id' => Product::factory(),
            'commission_type' => CommissionType::Percentage,
            'value' => 5,
            'priority' => 0,
            'valid_from' => now()->startOfDay(),
            'valid_until' => null,
            'status' => CommissionConfigurationStatus::Active,
            'internal_note' => null,
        ];
    }
}
