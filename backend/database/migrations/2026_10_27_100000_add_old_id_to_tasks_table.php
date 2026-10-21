<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for tasks (spec 0289): the nullable + unique
 * `old_id` the import engine reads for idempotence — a task already migrated
 * is skipped entirely, never duplicated. Added as its own migration because
 * `create_tasks_table` is already committed (backend.md §3), same pattern as
 * `2026_09_28_100100_add_old_id_to_task_templates_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
