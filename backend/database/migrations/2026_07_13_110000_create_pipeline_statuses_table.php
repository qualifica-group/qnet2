<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pipeline status lookup entity (spec 0023): a full-CRUD classification
 * (name/color/sort_order) used by BOTH Projects and Campaigns — hence
 * `pipeline_statuses`, not `project_statuses` (and not `states`, a name already
 * taken by the geo entity "Regione"). Referenced with `restrictOnDelete` by
 * `projects.pipeline_status_id` and `campaigns.pipeline_status_id` (BR-4):
 * defense in depth alongside the service-level 409 guard.
 *
 * `system_key`/`group` (spec 0039, D-2/D-5) pin the two mandatory system rows
 * seeded below — "Nuovo" opens the pipeline, "Chiuso" closes it — and classify
 * every row as open/pending/closed (App\Enums\StatusGroup). Custom rows sit
 * between them at sort_order 10, 20, ... (App\Services\Statuses\
 * StatusOrderManager).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
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
        Schema::dropIfExists('pipeline_statuses');
    }

    private function seedSystemRows(): void
    {
        $now = now();

        DB::table('pipeline_statuses')->insert([
            ['name' => 'Nuovo', 'color' => 'slate', 'sort_order' => 0, 'system_key' => 'new', 'group' => 'open', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Chiuso', 'color' => 'green', 'sort_order' => 10, 'system_key' => 'closed', 'group' => 'closed', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
