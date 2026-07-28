<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Opportunity status lookup entity (spec 0043): the "system statuses" shape
 * (spec 0039) shared with `pipeline_statuses` — `system_key`/`group` plus the
 * THREE mandatory system rows seeded here (BR-1): "Nuova" (`new`, open,
 * sort_order 0), "Chiusa con successo" (`won`, closed, sort_order 10), "Persa"
 * (`lost`, closed, sort_order 20 — ALWAYS last, D-2). `name` is UNIQUE (BR-3).
 * Referenced by `opportunities.opportunity_status_id` with `restrictOnDelete`
 * (BR-2), which is why this table is created before `opportunities`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_statuses', function (Blueprint $table) {
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
        Schema::dropIfExists('opportunity_statuses');
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table('opportunity_statuses')->insert([
            ['name' => 'Nuova', 'color' => 'slate', 'sort_order' => 0, 'system_key' => 'new', 'group' => 'open', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chiusa con successo', 'color' => 'green', 'sort_order' => 10, 'system_key' => 'won', 'group' => 'closed', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Persa', 'color' => 'red', 'sort_order' => 20, 'system_key' => 'lost', 'group' => 'closed', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
