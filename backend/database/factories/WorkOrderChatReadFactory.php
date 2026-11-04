<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChatRead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderChatRead>
 */
class WorkOrderChatReadFactory extends Factory
{
    protected $model = WorkOrderChatRead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'user_id' => User::factory(),
            'last_read_message_id' => null,
        ];
    }
}
