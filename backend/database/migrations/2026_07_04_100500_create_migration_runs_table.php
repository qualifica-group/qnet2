<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per external-data migration run (spec 0013): tracks the two-phase
 * (read-only preview -> queued import) flow's status and per-row counters for
 * a given source (`roles`, `users`, `business-functions`, `companies`,
 * `operational-sites` — the key registered in App\Migrations\
 * MigrationRegistry / config/migrations.php). `report` accumulates per-row
 * warnings (unresolved remap reference) and errors (failed row) so the
 * polling endpoint can surface them without a separate table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('migration_runs', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The parent "Import all" run (spec 0046). Nullable: a single-source
            // run (spec 0013) has no parent; nullOnDelete keeps the child rows if
            // the aggregate is ever removed.
            $table->foreignId('mass_migration_run_id')->nullable()->constrained()->nullOnDelete();

            $table->string('status');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('created_rows')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('report')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_runs');
    }
};
