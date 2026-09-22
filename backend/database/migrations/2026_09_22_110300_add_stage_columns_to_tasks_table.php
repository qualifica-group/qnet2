<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a root Task to the `work_order_stages` group it sits in on its
 * commessa's task board, plus its ordinal position inside that group (spec
 * 0146, D-2/D-3). Both nullable/defaulted, since a Task outside a commessa
 * or a sub-task never has a stage — D-3 enforces that at the FormRequest
 * layer, not the schema. `nullOnDelete`: deleting a stage demotes its Tasks
 * to "Senza fase" (D-2), never deletes them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('work_order_stage_id')
                ->nullable()
                ->after('work_order_id')
                ->constrained('work_order_stages')
                ->nullOnDelete();
            $table->unsignedInteger('stage_position')->default(0)->after('work_order_stage_id');

            $table->index(['work_order_id', 'work_order_stage_id', 'stage_position']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['work_order_id', 'work_order_stage_id', 'stage_position']);
            $table->dropConstrainedForeignId('work_order_stage_id');
            $table->dropColumn('stage_position');
        });
    }
};
