<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the task template header (spec 0172, D-12):
 * the nullable + unique `old_id` the import engine reads for idempotence — a
 * model already migrated is skipped entirely, never duplicated. No `old_id`
 * on `task_template_stages`/`task_template_items`: the import remaps their
 * legacy parents within the single record being imported, so they need no
 * anchor of their own. Added as its own migration because
 * `create_task_templates_table` is already committed (backend.md §3), same
 * pattern as `2026_08_04_120000_add_old_id_to_payment_methods_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('task_templates', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
