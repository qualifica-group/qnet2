<?php

namespace Database\Factories;

use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderReceiptLine>
 */
class PurchaseOrderReceiptLineFactory extends Factory
{
    protected $model = PurchaseOrderReceiptLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_receipt_id' => PurchaseOrderReceipt::factory(),
            'purchase_order_line_id' => PurchaseOrderLine::factory(),
            'quantity' => '1.000',
        ];
    }
}
