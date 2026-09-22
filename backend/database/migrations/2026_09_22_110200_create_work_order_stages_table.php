<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WorkOrderStage entity (spec 0146, D-2/D-4): the "Fase" a commessa's own
 * task board groups its root Tasks into — copied verbatim from the
 * `task_templates` stages at generation time (`WorkOrderTaskGenerator`), then
 * free-standing: created, renamed, reordered and deleted on the commessa
 * itself, with no catalog and no back-reference to the template it came
 * from.
 *
 * `closed_at`/`closed_by_id` (D-4): a stage closes only once none of its
 * Tasks are open (checked by the service, not the schema) and can reopen
 * freely — both columns are DELIBERATELY absent from the model's
 * `#[Fillable]`, written only by the close/reopen service action, the same
 * category as `WorkOrder::code`. `closed_by_id` is `nullOnDelete`: losing the
 * closing user just clears attribution, never blocks deleting them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('name', 191);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_stages');
    }
};
