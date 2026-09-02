<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0094 (D-2): full replacement, same discipline already applied to the
 * Opportunity (spec 0040 amendment rev.3). Every project that carries BOTH
 * `business_function_id` and `product_category_id` gets exactly one row in
 * `project_product_lines`; the two scalar columns are then dropped — no
 * dual source of truth survives.
 *
 * `down()` restores the STRUCTURE at its original position (right after
 * `pipeline_status_id` / `operational_site_id`, same nullable + nullOnDelete
 * as `2026_07_13_110100_create_projects_table.php`) and repopulates each
 * project from the FIRST row (ordered by id) of `project_product_lines`:
 * DESTRUCTIVE for any project that ended up with 2+ rows, the extra rows are
 * not recoverable into a single pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->migrateProjectsToProductLines();

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_function_id');
            $table->dropConstrainedForeignId('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('business_function_id')->nullable()
                ->after('pipeline_status_id')
                ->constrained('business_functions')
                ->nullOnDelete();

            $table->foreignId('product_category_id')->nullable()
                ->after('operational_site_id')
                ->constrained('product_categories')
                ->nullOnDelete();
        });

        $this->backfillProjectsFromFirstLine();
    }

    private function migrateProjectsToProductLines(): void
    {
        $now = now();

        $rows = DB::table('projects')
            ->whereNotNull('business_function_id')
            ->whereNotNull('product_category_id')
            ->get(['id', 'business_function_id', 'product_category_id'])
            ->map(fn ($project) => [
                'project_id' => $project->id,
                'business_function_id' => $project->business_function_id,
                'product_category_id' => $project->product_category_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

        if ($rows->isNotEmpty()) {
            DB::table('project_product_lines')->insert($rows->all());
        }
    }

    private function backfillProjectsFromFirstLine(): void
    {
        DB::table('project_product_lines')
            ->orderBy('id')
            ->get(['project_id', 'business_function_id', 'product_category_id'])
            ->unique('project_id')
            ->each(function ($line): void {
                DB::table('projects')
                    ->where('id', $line->project_id)
                    ->update([
                        'business_function_id' => $line->business_function_id,
                        'product_category_id' => $line->product_category_id,
                    ]);
            });
    }
};
