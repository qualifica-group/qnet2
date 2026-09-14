<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The recurrence RULE a Task can carry (spec 0120, D-1/D-3): a standalone
 * table, never a set of columns on `tasks` — the occurrences it produces are
 * autonomous Tasks (D-3), not sub-tasks, linked back via the sibling
 * `add_task_recurrence_id_to_tasks_table` migration.
 *
 * `weekdays`/`month_day` are both nullable because each is meaningful for
 * exactly one `frequency` (weekly/monthly); `ends_on`/`occurrence_count`
 * mirror that split for `ends`. No CHECK constraint enforces the pairing —
 * StoreTaskRequest/UpdateTaskRequest do, with `required_if`/
 * `prohibited_unless` (data_contract).
 *
 * `generated_until` is the command's own watermark (D-9): the newest
 * `end_date` this series has materialized, so `tasks:generate-recurrences`
 * resumes from there instead of re-walking the whole calendar on every run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_recurrences', function (Blueprint $table) {
            $table->id();
            $table->string('frequency', 16);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->string('ends', 16);
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('occurrence_count')->nullable();
            $table->date('generated_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_recurrences');
    }
};
