<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderChatMessage>
 */
class WorkOrderChatMessageFactory extends Factory
{
    protected $model = WorkOrderChatMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
