<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Task to the recurrence series it belongs to (spec 0120, D-3):
 * `nullOnDelete` so deleting the `task_recurrences` row (cancelling the
 * series, D-10) leaves every occurrence in place, merely unlinked — no
 * Task is ever removed by this FK.
 *
 * The UNIQUE pair (`task_recurrence_id`, `end_date`) is D-9's idempotency
 * guarantee AT THE DATABASE, not a hope resting on application code alone:
 * `tasks:generate-recurrences` re-run twice, or racing itself, cannot ever
 * insert the same occurrence date for the same series a second time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('task_recurrence_id')->nullable()->after('parent_task_id')
                ->constrained('task_recurrences')->nullOnDelete();

            $table->unique(['task_recurrence_id', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['task_recurrence_id', 'end_date']);
            $table->dropConstrainedForeignId('task_recurrence_id');
        });
    }
};
