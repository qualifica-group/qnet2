<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per staged file line of the unified import wizard (spec 0033),
 * written by `StageImportJob` and read back by `ProcessImportJob` (the
 * commit phase reads FROM this table, never re-parsing the source file) and
 * by the SSRM review grid. `raw_values` keeps the original file cell values,
 * `mapped_values` the field-id-resolved values, `extra_values` the
 * '__extra__' mapped columns, `resolved` the recognizer/dedup output (geo
 * ids, referent match id, name split), `messages` the per-row errors/
 * warnings surfaced to the review UI. cascadeOnDelete on `import_run_id` so
 * deleting a run cleans up its staged rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_run_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('import_runs')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('raw_values');
            $table->json('mapped_values');
            $table->json('extra_values')->nullable();
            $table->json('resolved')->nullable();
            $table->string('status');
            $table->json('messages')->nullable();
            $table->unsignedBigInteger('duplicate_of_id')->nullable();

            // Per-row duplicate resolution (spec 0036): `duplicate_meta` records
            // the matched referent/lead once staging (or a review edit) detects a
            // duplicate; `resolution` ("skip"|"create"|"update") is the operator's
            // per-row choice, read back by the commit phase instead of the global
            // dedup strategy.
            $table->json('duplicate_meta')->nullable();
            $table->string('resolution')->nullable();

            // Per-row Operator / Operational Site overrides (spec 0045): pin a
            // different value on ONE staged row, overriding the run's global
            // config just for that lead. nullOnDelete so a removed user/site
            // never blocks deleting or staging rows.
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('operational_site_id')->nullable()->constrained('operational_sites')->nullOnDelete();

            $table->boolean('is_edited')->default(false);
            $table->timestamps();

            $table->index(['import_run_id', 'status']);
            $table->index(['import_run_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_run_rows');
    }
};
