<?php

namespace Database\Factories;

use App\Enums\CommissionApplicationScope;
use App\Enums\CommissionConfigurationStatus;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Enums\SupplierCommissionDirection;
use App\Models\CommissionConfiguration;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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
            'commission_type' => CommissionType::Percentage,
            // Spec 0306 D-1: a Supplier rule always carries a direction.
            'supplier_commission_direction' => static fn (array $attributes): ?SupplierCommissionDirection => in_array($attributes['recipient_role'], [CommissionRecipientRole::Supplier, CommissionRecipientRole::Supplier->value], true)
                ? SupplierCommissionDirection::Paid
                : null,
            'value' => 5,
            'priority' => 0,
            'valid_from' => now()->startOfDay(),
            'valid_until' => null,
            'status' => CommissionConfigurationStatus::Active,
            'internal_note' => null,
        ];
    }

    /**
     * Spec 0430: the pivots are the only source of the scope lists. A rule of the
     * default PRODUCT scope gets one product, a PRODUCT_CATEGORY one gets one
     * category, unless the test attaches its own with forProducts()/forCategories().
     * Those attach through hasAttached(), which runs BEFORE the afterCreating
     * callbacks: an afterCreating sync would come after this default and leave
     * a stray product/category behind.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (CommissionConfiguration $configuration): void {
            if ($configuration->application_scope === CommissionApplicationScope::Product && $configuration->products()->doesntExist()) {
                $configuration->products()->attach(Product::factory()->create());
            }

            if ($configuration->application_scope === CommissionApplicationScope::ProductCategory && $configuration->productCategories()->doesntExist()) {
                $configuration->productCategories()->attach(ProductCategory::factory()->create());
            }
        });
    }

    /**
     * @param  iterable<int, int|Product>  $products
     */
    public function forProducts(iterable $products): static
    {
        return $this->state(['application_scope' => CommissionApplicationScope::Product])
            ->hasAttached(Product::query()->findMany($this->keys($products)), [], 'products');
    }

    /**
     * @param  iterable<int, int|ProductCategory>  $categories
     */
    public function forCategories(iterable $categories): static
    {
        return $this->state(['application_scope' => CommissionApplicationScope::ProductCategory])
            ->hasAttached(ProductCategory::query()->findMany($this->keys($categories)), [], 'productCategories');
    }

    /**
     * Scope 0089 D-1/D-2: a rule for one specific recipient. Pass a
     * non-RECIPIENT $scope to keep the destination orthogonal
     * (personal + PRODUCT / personal + PRODUCT_CATEGORY, D-2): the matching
     * pivot is then filled by configure().
     */
    public function forRecipient(
        string $recipientType,
        int $recipientId,
        CommissionApplicationScope $scope = CommissionApplicationScope::Recipient,
    ): static {
        return $this->state(fn (): array => [
            'application_scope' => $scope,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
        ]);
    }

    /**
     * @param  iterable<int, int|Model>  $items
     * @return list<int>
     */
    private function keys(iterable $items): array
    {
        return collect($items)->map(fn (int|Model $item): int => $item instanceof Model ? $item->getKey() : $item)->values()->all();
    }
}
