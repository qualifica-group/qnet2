<?php

namespace Database\Factories;

use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLinePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderLinePayment>
 */
class WorkOrderLinePaymentFactory extends Factory
{
    protected $model = WorkOrderLinePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'quote_line_id' => QuoteLine::factory(),
            'work_order_payment_status_id' => null,
            'payment_agreement' => null,
            'has_unpaid' => false,
        ];
    }
}
