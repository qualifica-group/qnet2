<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual order of a task's sub-tasks (spec 0155, D-4), independent of the
 * work-order board's `stage_position`. Indexed with `parent_task_id` because
 * the sub-task list is always read as "children of X, in order".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('subtask_position')->default(0)->after('parent_task_id');
            $table->index(['parent_task_id', 'subtask_position']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['parent_task_id', 'subtask_position']);
            $table->dropColumn('subtask_position');
        });
    }
};
