<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the commessa's own "Stato pagamento" (spec 0403, D-4): the payment
 * status lives on the lines only. Its value is first copied onto the lines of
 * that commessa that have none (an existing line status wins; a line without
 * a payment row gets one), then the column goes. Query builder only: no
 * activity log, no notification. down() restores the column, empty.
 */
return new class extends Migration
{
    private const int CHUNK_SIZE = 500;

    public function up(): void
    {
        DB::table('work_orders')
            ->whereNotNull('work_order_payment_status_id')
            ->select(['id', 'work_order_payment_status_id'])
            ->chunkById(self::CHUNK_SIZE, function ($workOrders): void {
                foreach ($workOrders as $workOrder) {
                    $this->fold((int) $workOrder->id, (int) $workOrder->work_order_payment_status_id);
                }
            });

        // SQLite rebuilds `work_orders` to drop the column: with constraints on, the cascade would wipe the line payments.
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_payment_status_id');
        }));
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('work_order_payment_status_id')
                ->nullable()
                ->after('work_order_workflow_status_id')
                ->constrained('work_order_payment_statuses')
                ->restrictOnDelete();
        }));
    }

    private function fold(int $workOrderId, int $statusId): void
    {
        DB::table('work_order_line_payments')
            ->where('work_order_id', $workOrderId)
            ->whereNull('work_order_payment_status_id')
            ->update(['work_order_payment_status_id' => $statusId]);

        $existing = DB::table('work_order_line_payments')->where('work_order_id', $workOrderId)->pluck('quote_line_id')->all();
        $now = now();

        $missing = DB::table('quote_line_work_order')
            ->where('work_order_id', $workOrderId)
            ->whereNotIn('quote_line_id', $existing)
            ->pluck('quote_line_id')
            ->map(fn ($lineId): array => [
                'work_order_id' => $workOrderId,
                'quote_line_id' => $lineId,
                'work_order_payment_status_id' => $statusId,
                'payment_agreement' => null,
                'has_unpaid' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        foreach (array_chunk($missing, self::CHUNK_SIZE) as $rows) {
            DB::table('work_order_line_payments')->insert($rows);
        }
    }
};
