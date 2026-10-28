<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0322 (D-1): the commessa gets its own "Stato lavorazione", a row of
 * the criteria-resolved `work_order_workflow_statuses` set. Same four-phase
 * discipline as the quotes one (add nullable -> backfill -> tighten ->
 * index): the NOT NULL ALTER never runs before every row satisfies it.
 *
 * Every EXISTING work order is backfilled onto the `open` row of the GLOBAL
 * default set: no workflow exists yet at this point, so that IS the set the
 * resolver would assign.
 *
 * `down()` drops the column: the per-order assignment is not recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('work_order_workflow_status_id')->nullable()
                ->constrained('work_order_workflow_statuses')
                ->restrictOnDelete();
        });

        $this->backfillWithGlobalOpenStatus();

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('work_order_workflow_status_id')->nullable(false)->change();
            $table->index('work_order_workflow_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->dropForeign(['work_order_workflow_status_id']);
            $table->dropIndex(['work_order_workflow_status_id']);
            $table->dropColumn('work_order_workflow_status_id');
        });
    }

    private function backfillWithGlobalOpenStatus(): void
    {
        $globalOpenId = DB::table('work_order_workflow_statuses')
            ->whereNull('work_order_workflow_id')
            ->where('system_key', 'open')
            ->value('id');

        if ($globalOpenId === null) {
            throw new RuntimeException(
                'Global default work order workflow status set is missing its "open" row; '
                .'cannot backfill work_orders.work_order_workflow_status_id.'
            );
        }

        DB::table('work_orders')->update(['work_order_workflow_status_id' => $globalOpenId]);
    }
};
