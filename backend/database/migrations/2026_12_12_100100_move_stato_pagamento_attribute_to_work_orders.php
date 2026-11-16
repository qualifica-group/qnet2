<?php

use App\Services\WorkOrders\WorkOrderPaymentStatusAttributeMover;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0402: the "Stato Pagamento" flexible field of the commessa becomes the
 * `work_order_payment_status_id` column (WorkOrderPaymentStatusAttributeMover).
 *
 * down() puts the values back into the flexible values (legacy key = the
 * status `old_id`); the deleted "Stato Pagamento" field, its category
 * assignments and layout placements are not rebuilt (re-import the field from
 * the legacy, which no longer sends it, or create it again by hand).
 */
return new class extends Migration
{
    public function up(): void
    {
        app(WorkOrderPaymentStatusAttributeMover::class)->handle();
    }

    public function down(): void
    {
        DB::table('work_orders')
            ->join('work_order_payment_statuses', 'work_order_payment_statuses.id', '=', 'work_orders.work_order_payment_status_id')
            ->whereNotNull('work_order_payment_statuses.old_id')
            ->select(['work_orders.id', 'work_orders.attribute_values', 'work_order_payment_statuses.old_id'])
            ->orderBy('work_orders.id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    $values = json_decode((string) $row->attribute_values, true);
                    $values = is_array($values) ? $values : [];
                    $values[WorkOrderPaymentStatusAttributeMover::ATTRIBUTE_CODE] = (string) $row->old_id;

                    DB::table('work_orders')->where('id', $row->id)->update([
                        'attribute_values' => json_encode($values, JSON_UNESCAPED_UNICODE),
                        'work_order_payment_status_id' => null,
                    ]);
                }
            });
    }
};
