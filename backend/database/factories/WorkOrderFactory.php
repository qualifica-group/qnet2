<?php

namespace Database\Factories;

use App\Enums\WorkOrderType;
use App\Models\Quote;
use App\Models\User;
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
            // Spec 0096: NOT NULL, so it has a real default here. The
            // Responsabili live in their own pivot and are attached by the
            // service (or explicitly by a test), never mass-assigned.
            'start_date' => fake()->date(),
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
     * Spec 0096: attach $count Responsabili, the relation every commessa has
     * at least one of in production — a factory-made work order has none
     * unless a test asks, since the pivot cannot be mass-assigned.
     */
    public function withSupervisors(int $count = 1): static
    {
        return $this->afterCreating(function (WorkOrder $workOrder) use ($count): void {
            $workOrder->supervisors()->sync(User::factory()->count($count)->create()->pluck('id')->all());
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
