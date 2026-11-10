<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The working status a commessa held when its contract was terminated
     * (legacy `orders.last_status`), so reopening the contract can restore it.
     */
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('status_before_contract_termination_id')
                ->nullable()
                ->after('force_close_reason')
                ->constrained('work_order_workflow_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_before_contract_termination_id');
        });
    }
};
