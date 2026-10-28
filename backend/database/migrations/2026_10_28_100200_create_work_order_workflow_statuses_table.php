<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Work order workflow statuses (spec 0322): the "stato lavorazione" pick
 * list of a WorkOrder, distinct from the computed `WorkOrderStatus`. Each
 * row belongs to one workflow (`work_order_workflow_id`) OR to the GLOBAL
 * default set when the column is NULL (the fallback of every work order
 * matching no active workflow), seeded below.
 *
 * `group` holds a `WorkOrderStatus` value (D-7); `progress_percentage`
 * (D-9) is configuration only; `old_id` is the legacy `progress.id` set by
 * the migration source (D-11).
 *
 * PERCHE (caveat): NULL is DISTINCT in a UNIQUE index, so the
 * (work_order_workflow_id, system_key) unique does not protect the global
 * set's rows; that invariant is held by the service that syncs the set,
 * exactly as for `quote_workflow_statuses`. `name` is deliberately NOT
 * unique: the legacy catalogue repeats names inside one category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_workflow_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_workflow_id')->nullable()->constrained('work_order_workflows')->cascadeOnDelete();
            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('group', 16)->default('open');
            $table->unsignedTinyInteger('progress_percentage')->nullable();
            $table->string('system_key', 16)->nullable();
            $table->unsignedBigInteger('old_id')->nullable();
            $table->timestamps();

            $table->unique(['work_order_workflow_id', 'system_key'], 'wow_statuses_workflow_system_key_unique');
            $table->index(['work_order_workflow_id', 'sort_order'], 'wow_statuses_workflow_sort_index');
            $table->index('old_id');
        });

        $this->seedGlobalDefaultSet();
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_workflow_statuses');
    }

    /**
     * Seeds the GLOBAL default set (D-2): `open` first, then the pinned tail
     * `completed` and `closed`, each with the group equal to its key.
     * Idempotent on the (NULL workflow, system_key) pair.
     */
    private function seedGlobalDefaultSet(): void
    {
        $now = now();

        $rows = [
            ['name' => 'Aperta', 'sort_order' => 0, 'system_key' => 'open', 'group' => 'open'],
            ['name' => 'Completata', 'sort_order' => 10, 'system_key' => 'completed', 'group' => 'completed'],
            ['name' => 'Chiusa', 'sort_order' => 20, 'system_key' => 'closed', 'group' => 'closed'],
        ];

        foreach ($rows as $row) {
            DB::table('work_order_workflow_statuses')->updateOrInsert(
                ['work_order_workflow_id' => null, 'system_key' => $row['system_key']],
                $row + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
