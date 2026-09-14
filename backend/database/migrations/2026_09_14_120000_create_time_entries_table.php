<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time entry (spec 0122, D-4): a FLAT row, one per segnatempo — unlike qnet's
 * day+interval shape, which reuses and overwrites the shared day. `user_id`
 * is `cascadeOnDelete` (owned-by-user record, same pattern as
 * `table_filter_views`/`import_runs`): a deleted user takes their own
 * segnatempo with them.
 *
 * `task_type_id` is `restrictOnDelete` like every other Task configurator FK
 * (mirrors `tasks.task_type_id`): a type in use may not be removed. The four
 * optional record links (`registry_id`/`opportunity_id`/`work_order_id`/
 * `task_id`) are `nullOnDelete` — losing the referenced row just clears the
 * field, same as on `tasks`. With `task_id` set, D-5 makes the server
 * override `title` and the three links from the Task; the columns still
 * exist independently so a segnatempo keeps its own record links even if the
 * Task is later cleared or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('title', 191);
            $table->foreignId('task_type_id')->constrained('task_types')->restrictOnDelete();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('minutes');
            $table->text('notes')->nullable();
            $table->foreignId('registry_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
