<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of the template row's working status on the generated Task (spec
 * 0323, D-4): read when the Task closes positively to advance its commessa.
 * Nullable (manual tasks never carry one), `nullOnDelete` like the template
 * column it is copied from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('work_order_workflow_status_id')
                ->nullable()
                ->after('work_order_stage_id')
                ->constrained('work_order_workflow_statuses')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_order_workflow_status_id');
        });
    }
};
