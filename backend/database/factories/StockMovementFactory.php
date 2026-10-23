<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * A LOAD by default (destination only).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => StockMovementType::Load,
            'product_id' => Product::factory()->internalUse(),
            'quantity' => '1.000',
            'from_operational_site_id' => null,
            'from_user_id' => null,
            'to_operational_site_id' => OperationalSite::factory(),
            'to_user_id' => null,
            'moved_on' => now()->toDateString(),
            'note' => null,
            'created_by' => User::factory(),
        ];
    }
}
