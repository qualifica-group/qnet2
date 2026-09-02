<?php

namespace Database\Factories;

use App\Enums\WorkOrderType;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quote_id' => Quote::factory(),
            'title' => fake()->words(3, true).' work order',
            'type' => fake()->randomElement(WorkOrderType::cases()),
            'callback_date' => fake()->optional()->date(),
            'description' => fake()->optional()->sentence(),
            'internal_notes' => fake()->optional()->sentence(),
            'is_force_closed' => false,
            'force_close_reason' => null,
        ];
    }

    /**
     * `code` (D-1: COM-0001...) is service-generated in production and
     * deliberately NOT in the model's #[Fillable], so it must be assigned
     * directly (property assignment bypasses mass-assignment guarding) after
     * the instance is made, not through the fillable `definition()` array —
     * mirrors QuoteFactory::configure().
     */
    public function configure(): static
    {
        return $this->afterMaking(function (WorkOrder $workOrder): void {
            $workOrder->code ??= sprintf('COM-%04d', fake()->unique()->numberBetween(1, 999999));
        });
    }

    /**
     * D-4: `force_close_reason` is mandatory whenever `is_force_closed` is
     * true.
     */
    public function forceClosed(): static
    {
        return $this->state(fn (): array => [
            'is_force_closed' => true,
            'force_close_reason' => fake()->sentence(),
        ]);
    }
}
