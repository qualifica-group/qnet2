<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderMergeAction;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderMergeLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderMergeLog>
 */
class PurchaseOrderMergeLogFactory extends Factory
{
    protected $model = PurchaseOrderMergeLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'destination_purchase_order_id' => PurchaseOrder::factory(),
            'source_purchase_order_id' => null,
            'source_code' => '1/'.now()->format('Y'),
            'source_snapshot' => [],
            'moved_line_ids' => [],
            'source_action' => PurchaseOrderMergeAction::Deleted,
            'performed_by' => User::factory(),
        ];
    }
}
