<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quote status lookup entity (spec 0065, D-2): a plain clone of the simple
 * `opportunity_statuses` configurator — name/color/sort_order plus the
 * `system_key`/`group` system-statuses shape (spec 0039) shared across
 * modules. NO workflow configurator (matching criteria, mandatory notes) is
 * introduced for this module: the `opportunity_workflows` equivalent is
 * explicitly out of scope (D-2). The THREE system rows are seeded here:
 * "Bozza" (`new`, open, sort_order 0), "Accettata" (`won`, closed,
 * sort_order 10), "Rifiutata" (`lost`, closed, sort_order 20 — always last).
 * `name` is UNIQUE. Referenced by `quotes.quote_status_id` with
 * `restrictOnDelete`, which is why this table is created before `quotes`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('color', 32)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('system_key', 16)->nullable()->unique();
            $table->string('group', 16)->default('open');
            $table->timestamps();
        });

        $this->seedSystemRows();
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_statuses');
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table('quote_statuses')->insert([
            ['name' => 'Bozza', 'color' => 'slate', 'sort_order' => 0, 'system_key' => 'new', 'group' => 'open', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Accettata', 'color' => 'green', 'sort_order' => 10, 'system_key' => 'won', 'group' => 'closed', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rifiutata', 'color' => 'red', 'sort_order' => 20, 'system_key' => 'lost', 'group' => 'closed', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
