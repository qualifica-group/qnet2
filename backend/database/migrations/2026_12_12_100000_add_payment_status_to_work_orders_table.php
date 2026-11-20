<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The commessa's own "Stato pagamento" (spec 0402), one of the configured
 * `work_order_payment_statuses`, for every commessa instead of a flexible
 * field of some categories. RESTRICT like the per-line payments: a status in
 * use is never silently blanked (InUseGuard answers 409 first).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('work_order_payment_status_id')
                ->nullable()
                ->after('work_order_workflow_status_id')
                ->constrained('work_order_payment_statuses')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_payment_status_id');
        });
    }
};
