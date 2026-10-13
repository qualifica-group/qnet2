<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0243: a work order or a proforma request that already feeds the active
 * accounting must not disappear through a DB-level cascade / set-null. The
 * services guard the delete; the FK is the last line of defence. The unique
 * index on invoices.proforma_request_id is untouched (only the FK is rebuilt).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewire(restrict: true);
    }

    public function down(): void
    {
        $this->rewire(restrict: false);
    }

    private function rewire(bool $restrict): void
    {
        Schema::table('proforma_requests', function (Blueprint $table) use ($restrict): void {
            $table->dropForeign(['work_order_id']);
            $fk = $table->foreign('work_order_id')->references('id')->on('work_orders');
            $restrict ? $fk->restrictOnDelete() : $fk->cascadeOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) use ($restrict): void {
            $table->dropForeign(['proforma_request_id']);
            $table->dropForeign(['work_order_id']);

            $proforma = $table->foreign('proforma_request_id')->references('id')->on('proforma_requests');
            $workOrder = $table->foreign('work_order_id')->references('id')->on('work_orders');
            $restrict ? $proforma->restrictOnDelete() : $proforma->nullOnDelete();
            $restrict ? $workOrder->restrictOnDelete() : $workOrder->nullOnDelete();
        });
    }
};
