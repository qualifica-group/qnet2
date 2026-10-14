<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderReceipt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderReceipt>
 */
class PurchaseOrderReceiptFactory extends Factory
{
    protected $model = PurchaseOrderReceipt::class;

    /**
     * Header only: the lines and the order's received_quantity are written by the
     * receipt service (or the purchaseOrderReceive test helper).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'receipt_date' => now()->toDateString(),
            'delivery_note_number' => null,
            'delivery_note_date' => null,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
