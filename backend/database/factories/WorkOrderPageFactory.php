<?php

namespace Database\Factories;

use App\Models\WorkOrder;
use App\Models\WorkOrderPage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderPage>
 */
class WorkOrderPageFactory extends Factory
{
    protected $model = WorkOrderPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'content' => null,
            'version' => 0,
        ];
    }
}
