<?php

namespace Database\Factories;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
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

    /**
     * Scope 0089 D-1/D-2: a rule for one specific recipient. Clears
     * product/category so the scope check accepts a RECIPIENT rule as-is;
     * pass a non-RECIPIENT $scope to keep the destination orthogonal
     * (personal + PRODUCT / personal + PRODUCT_CATEGORY, D-2).
     */
    public function forRecipient(
        string $recipientType,
        int $recipientId,
        CommissionApplicationScope $scope = CommissionApplicationScope::Recipient,
    ): static {
        return $this->state(fn (): array => [
            'application_scope' => $scope,
            'product_category_id' => $scope === CommissionApplicationScope::ProductCategory
                ? ProductCategory::factory()
                : null,
            'product_id' => $scope === CommissionApplicationScope::Product
                ? Product::factory()
                : null,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
        ]);
    }
}
