<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic per-table CSV import run (spec 0012): one row per upload, tracking
 * the two-phase (validate dry-run -> confirm commit) flow's status, counts,
 * bounded preview and the downloadable errors report.
 *
 * `resource` is the `{domain}` key (App\Imports\ImportRegistry /
 * config/imports.php) the run was started against — a plain string, NOT a
 * foreign key, mirroring the generic Tables framework's domain string so the
 * import engine never couples to a specific resource's schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->string('resource')->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->string('original_filename');
            $table->string('stored_path');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('imported_rows')->nullable();
            $table->string('error_report_path')->nullable();
            $table->json('preview')->nullable();

            // Unified import wizard state (spec 0033), nullable so the 5 legacy
            // domains (spec 0012) are unaffected: the analyze/configure step
            // output, the chosen duplicate handling, the row counters mirroring
            // `import_run_rows` staging outcomes, and the notification guard.
            // `error_rows` from the API contract is NOT a column: ImportRunResource
            // derives it from `invalid_rows`.
            $table->json('detected_columns')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('global_config')->nullable();
            $table->string('dedup_strategy')->nullable();
            $table->unsignedInteger('warning_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('modified_rows')->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('error_count')->default(0);

            // The operator's confirm-step choice (spec 0045): whether a
            // CREATE-branch row should also spawn an Opportunity.
            $table->boolean('convert_to_opportunity')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_runs');
    }
};
