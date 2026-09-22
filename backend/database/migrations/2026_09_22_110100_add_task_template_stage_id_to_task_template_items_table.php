<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a `task_template_items` row to the `task_template_stages` group it
 * sits in (spec 0146, D-2). Nullable: null means "Senza fase" — the default
 * for a row that predates this column and for one an admin deliberately
 * leaves ungrouped. `nullOnDelete`: removing a stage (full-sync omission,
 * AC-002) demotes its items to "Senza fase" instead of deleting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->foreignId('task_template_stage_id')
                ->nullable()
                ->after('task_template_id')
                ->constrained('task_template_stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_template_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('task_template_stage_id');
        });
    }
};
