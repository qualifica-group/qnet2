<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task status lookup entity (spec 0101, D-4/D-5): the configurator behind
 * `tasks.task_status_id`. Same pure-lookup shape as the sibling
 * `task_types`/`task_categories`/`task_priorities`/`task_importances`, PLUS
 * two columns: `system_key` (the only handle the application logic ever
 * reads — the label never enters a condition) and `completion_percentage`
 * (the Task's completion is a PROJECTION of its status, D-6 — `tasks` itself
 * has no such column).
 *
 * Unlike `contract_statuses`/`pipeline_statuses`, there is NO `group` column:
 * the six system keys already ARE the phases, so a separate `group` would
 * duplicate that fact in a second place (D-5).
 *
 * The six system rows are seeded HERE, in the migration, so they exist
 * before any backfill or seed can reference them — same precedent as
 * `create_contract_statuses_table`. `closed_negative`'s 0% is only its
 * INITIAL value: every row's percentage stays admin-configurable.
 */
return new class extends Migration
{
    private const string TABLE = 'task_statuses';

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('system_key', 16)->nullable()->unique();
            $table->unsignedTinyInteger('completion_percentage')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $this->seedSystemRows();
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table(self::TABLE)->insert([
            ['name' => 'Aperto', 'description' => null, 'color' => 'slate', 'icon' => null, 'sort_order' => 0, 'is_active' => true, 'system_key' => 'open', 'completion_percentage' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'In corso', 'description' => null, 'color' => 'blue', 'icon' => null, 'sort_order' => 10, 'is_active' => true, 'system_key' => 'in_progress', 'completion_percentage' => 25, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'In sospeso', 'description' => null, 'color' => 'amber', 'icon' => null, 'sort_order' => 20, 'is_active' => true, 'system_key' => 'pending', 'completion_percentage' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'In validazione', 'description' => null, 'color' => 'orange', 'icon' => null, 'sort_order' => 30, 'is_active' => true, 'system_key' => 'in_validation', 'completion_percentage' => 75, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chiuso positivo', 'description' => null, 'color' => 'green', 'icon' => null, 'sort_order' => 40, 'is_active' => true, 'system_key' => 'closed_positive', 'completion_percentage' => 100, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chiuso negativo', 'description' => null, 'color' => 'red', 'icon' => null, 'sort_order' => 50, 'is_active' => true, 'system_key' => 'closed_negative', 'completion_percentage' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
