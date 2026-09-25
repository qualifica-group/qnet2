<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "Fase" snapshot on a TimeEntry (spec 0163, D-1/D-3): with `task_id`
 * set the server always derives this column from the linked Task's OWN
 * `work_order_stage_id` (input ignored — `TimeEntryLinkResolver::fromTask()`),
 * otherwise it is an optional, explicit choice among the commessa's OPEN
 * stages (`TimeEntryLinkResolver::standalone()`). `nullOnDelete`, the same
 * category as every other optional record link on this table (deleting a
 * stage just demotes its voci to "Senza fase", never blocks the delete or
 * takes the voce down with it).
 *
 * The one-off backfill (D-3) copies each linked Task's CURRENT stage onto
 * its existing voci — a snapshot taken once, at migration time, not an
 * ongoing sync (D-2 forbids realigning a voce when its Task later changes
 * stage). `down()` intentionally does not try to reconstruct which rows were
 * touched: dropping the column discards the snapshot outright, the same
 * "structure reversible, backfilled data is not" contract every other D-3
 * data backfill in this codebase already follows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->foreignId('work_order_stage_id')
                ->nullable()
                ->after('task_id')
                ->constrained('work_order_stages')
                ->nullOnDelete();

            $table->index('work_order_stage_id');
        });

        $this->backfillFromTasks();
    }

    public function down(): void
    {
        Schema::table('time_entries', function (Blueprint $table) {
            $table->dropIndex(['work_order_stage_id']);
            $table->dropConstrainedForeignId('work_order_stage_id');
        });
    }

    /**
     * D-3: every existing voce whose linked Task currently sits in a stage
     * inherits that stage. Chunked by Task rather than by TimeEntry — a
     * commessa's board is small, its segnatempo can be large — so this never
     * loads more than one page of Tasks into memory.
     */
    private function backfillFromTasks(): void
    {
        DB::table('tasks')
            ->whereNotNull('work_order_stage_id')
            ->orderBy('id')
            ->select('id', 'work_order_stage_id')
            ->chunkById(500, function ($tasks): void {
                foreach ($tasks as $task) {
                    DB::table('time_entries')
                        ->where('task_id', $task->id)
                        ->update(['work_order_stage_id' => $task->work_order_stage_id]);
                }
            });
    }
};
