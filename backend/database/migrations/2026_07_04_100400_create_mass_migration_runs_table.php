<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per "Import all" run (spec 0046): the aggregate over the per-source
 * child MigrationRuns it executes in order. `sources` snapshots the enabled
 * source keys (in planned order) at launch time, so the polling UI can render
 * not-yet-started sources and stop-on-failure state; `status` reuses
 * App\Enums\MigrationStatus (pending -> processing -> completed | failed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mass_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('sources');
            $table->string('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mass_migration_runs');
    }
};
