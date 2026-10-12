<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\PurchaseOrder;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Header only; lines are added through PurchaseOrderLineFactory (the totals
     * stay at zero until the service or the test computes them). `number` is
     * unique per (company, year) within a test run.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $orderDate = now();

        return [
            'year' => (int) $orderDate->format('Y'),
            'company_id' => Company::factory(),
            'number' => fn (array $attributes): int => (int) PurchaseOrder::query()
                ->where('company_id', $attributes['company_id'])
                ->where('year', $attributes['year'])
                ->max('number') + 1,
            'supplier_id' => Registry::factory()->supplier(),
            'company_site_id' => fn (array $attributes): int => CompanySite::factory()->create(['company_id' => $attributes['company_id']])->id,
            'order_date' => $orderDate->toDateString(),
            'description' => fake()->sentence(4),
            'is_internal_user' => false,
            'status' => PurchaseOrderStatus::Draft,
            'created_by' => User::factory(),
        ];
    }

    public function withStatus(PurchaseOrderStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
