<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contract status lookup entity (spec 0072, D-2/D-5): the configurator behind
 * `contracts.contract_status_id`. Combines the `description`/`is_active`
 * addition already used by `reward_statuses` (spec 0060) with the
 * `is_default` exclusive-default shape of `document_layouts` (spec 0069,
 * `DocumentLayoutDefaultManager`) and the `group`/`system_key` system-statuses
 * shape shared by every other configurator (spec 0039), classified by
 * `App\Enums\ContractStatusGroup` — a NEW enum dedicated to this module (D-5),
 * not a reuse of `QuoteStatusGroup`.
 *
 * Unlike `quote_statuses`' three system rows, this table carries FOUR: the
 * HEAD "Da validare" (`new`, open, is_default) and a THREE-row TAIL —
 * "Sospeso" (`suspended`, pending), "Annullato" (`cancelled`, closed_lost),
 * "Disdetto" (`terminated`, closed_lost) — App\Models\ContractStatus::
 * SYSTEM_TAIL_KEYS, in that declared order (App\Services\Statuses\
 * StatusOrderManager). "Da programmare"/"Programmato"/"In scadenza" are
 * plain, deletable custom rows (D-2): no domain action resolves them by
 * system_key, so "Valida"/"Programma" take their destination status from the
 * client instead.
 *
 * The 7 rows are seeded here, in the migration itself (same precedent as
 * `create_quote_statuses_table`), so they exist even on a database created
 * without the demo seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191)->unique();
            $table->string('description', 500)->nullable();
            $table->string('color', 32);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('system_key', 16)->nullable()->unique();
            $table->string('group', 16)->default('open');
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $this->seedSystemRows();
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_statuses');
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table('contract_statuses')->insert([
            ['name' => 'Da validare', 'description' => null, 'color' => 'amber', 'sort_order' => 0, 'is_active' => true, 'is_default' => true, 'system_key' => 'new', 'group' => 'open', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Da programmare', 'description' => null, 'color' => 'sky', 'sort_order' => 10, 'is_active' => true, 'is_default' => false, 'system_key' => null, 'group' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Programmato', 'description' => null, 'color' => 'blue', 'sort_order' => 20, 'is_active' => true, 'is_default' => false, 'system_key' => null, 'group' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'In scadenza', 'description' => null, 'color' => 'orange', 'sort_order' => 30, 'is_active' => true, 'is_default' => false, 'system_key' => null, 'group' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sospeso', 'description' => null, 'color' => 'slate', 'sort_order' => 40, 'is_active' => true, 'is_default' => false, 'system_key' => 'suspended', 'group' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Annullato', 'description' => null, 'color' => 'red', 'sort_order' => 50, 'is_active' => true, 'is_default' => false, 'system_key' => 'cancelled', 'group' => 'closed_lost', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Disdetto', 'description' => null, 'color' => 'red', 'sort_order' => 60, 'is_active' => true, 'is_default' => false, 'system_key' => 'terminated', 'group' => 'closed_lost', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
