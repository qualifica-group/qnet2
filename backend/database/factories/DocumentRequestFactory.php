<?php

namespace Database\Factories;

use App\Enums\DocumentRequestDeliveryStatus;
use App\Models\DocumentRequest;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    protected $model = DocumentRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'checklist_code' => strtoupper(fake()->unique()->bothify('CHK-####??')),
            'checklist_name' => fake()->words(3, true),
            'delivery_status' => DocumentRequestDeliveryStatus::Pending,
        ];
    }
}
