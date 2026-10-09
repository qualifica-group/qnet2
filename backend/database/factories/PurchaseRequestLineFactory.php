<?php

namespace Database\Factories;

use App\Enums\PurchaseRequestLineStatus;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseRequestLine>
 */
class PurchaseRequestLineFactory extends Factory
{
    protected $model = PurchaseRequestLine::class;

    /**
     * Amounts are consistent with a 1.000 x 100.00 line without VAT.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_request_id' => PurchaseRequest::factory(),
            'position' => 1,
            'description' => fake()->sentence(3),
            'quantity' => '1.000',
            'unit_price' => '100.00',
            'taxable_amount' => '100.00',
            'vat_amount' => '0.00',
            'total_amount' => '100.00',
            'status' => PurchaseRequestLineStatus::PendingApproval,
        ];
    }

    public function withStatus(PurchaseRequestLineStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
