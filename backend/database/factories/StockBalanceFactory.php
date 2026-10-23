<?php

namespace Database\Factories;

use App\Models\OperationalSite;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->internalUse(),
            'operational_site_id' => OperationalSite::factory(),
            'user_id' => null,
            'quantity' => '1.000',
            'last_moved_on' => now()->toDateString(),
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
