<?php

namespace Database\Factories;

use App\Enums\SupplierCommissionDirection;
use App\Models\ProductTypology;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductTypology>
 */
class ProductTypologyFactory extends Factory
{
    protected $model = ProductTypology::class;

    /** Incrementing counter backing `code`'s uniqueness. */
    private static int $nextSuffix = 1;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = self::$nextSuffix++;

        return [
            'name' => fake()->unique()->words(2, true),
            'code' => 'typology_'.$suffix,
            'description' => fake()->optional()->sentence(),
        ];
    }

    /** Supplier commission switched on with the given direction (spec 0202, D-7). */
    public function supplierCommission(SupplierCommissionDirection $direction = SupplierCommissionDirection::Paid): static
    {
        return $this->state(fn (): array => [
            'supplier_commission_enabled' => true,
            'supplier_commission_direction' => $direction,
        ]);
    }
}
