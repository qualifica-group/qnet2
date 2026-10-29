<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a `task_template_items` row to the work order working status its
 * generated Task moves the commessa onto when closed positively (spec 0323,
 * D-4). Nullable: most rows drive nothing. `nullOnDelete`: removing a status
 * or its workflow unlinks the row instead of blocking the configurator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->foreignId('work_order_workflow_status_id')
                ->nullable()
                ->after('task_status_id')
                ->constrained('work_order_workflow_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_workflow_status_id');
        });
    }
};
