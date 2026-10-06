<?php

namespace Database\Factories;

use App\Enums\ProformaRequestKind;
use App\Enums\ProformaRequestStatus;
use App\Models\ProformaRequest;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProformaRequest>
 */
class ProformaRequestFactory extends Factory
{
    protected $model = ProformaRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_order_id' => WorkOrder::factory(),
            'kind' => ProformaRequestKind::Consultancy,
            'supplier_id' => null,
            'payment_method_id' => null,
            'status' => ProformaRequestStatus::Pending,
            'issued_at' => null,
            'note' => fake()->sentence(),
            'assigned_to' => User::factory(),
            'assigned_by' => User::factory(),
        ];
    }

    public function issued(): static
    {
        return $this->state(fn (): array => [
            'status' => ProformaRequestStatus::Issued,
            'issued_at' => now(),
        ]);
    }
}
