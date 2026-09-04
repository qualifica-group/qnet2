<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task entity (spec 0101): a trackable, hierarchical activity, linkable to
 * Anagrafica/Referente/Opportunita'/Commessa, with a creator, an optional
 * requester, and two user pivots (assignees/watchers, see the sibling
 * `task_assignee`/`task_watcher` migrations).
 *
 * `creator_id` is NOT NULL and `restrictOnDelete` — server-set, immutable,
 * never client-writable (D-10). `parent_task_id` and the six configurator
 * FKs are `restrictOnDelete` (D-8/AC-003): nothing a Task depends on may be
 * removed while it exists. The optional record links (Anagrafica, Referente,
 * Opportunita', Commessa, Richiedente) are `nullOnDelete`: losing the
 * referenced row just clears the field.
 *
 * NO `completion_percentage` column: it is a PROJECTION of `task_status_id`,
 * computed by `App\Services\Tasks\TaskStatusResolver` (D-6). NO recurrence
 * column of any kind: out of scope in this phase (D-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->text('description')->nullable();

            $table->foreignId('registry_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('referent_id')->nullable()->constrained('referents')->nullOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->restrictOnDelete();
            $table->foreignId('task_type_id')->nullable()->constrained('task_types')->restrictOnDelete();
            $table->foreignId('task_status_id')->constrained('task_statuses')->restrictOnDelete();
            $table->foreignId('task_priority_id')->nullable()->constrained('task_priorities')->restrictOnDelete();
            $table->foreignId('task_importance_id')->nullable()->constrained('task_importances')->restrictOnDelete();
            $table->foreignId('task_category_id')->nullable()->constrained('task_categories')->restrictOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();

            $table->boolean('is_blocked')->default(false);
            $table->boolean('requires_closure_feedback')->default(false);
            $table->text('closure_feedback')->nullable();

            $table->timestamps();

            $table->index('task_status_id');
            $table->index('registry_id');
            $table->index('parent_task_id');
            $table->index('start_date');
            $table->index('end_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
