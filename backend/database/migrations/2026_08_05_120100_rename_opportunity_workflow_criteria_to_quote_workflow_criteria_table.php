<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-6), continuing the rename started by
 * `2026_08_05_120000_rename_opportunity_workflows_to_quote_workflows_table`:
 * the criterion rows follow their parent table. Three moves, in the order
 * that keeps every intermediate state valid:
 *  1. rename the table itself;
 *  2. rename the FK column — SQLite's native RENAME COLUMN updates every
 *     reference to it within the table's own schema (the FK clause and the
 *     unique index below) as part of the same statement, so the index below
 *     is renamed AFTER, once it already points at the new column name; MySQL
 *     keeps FK/index definitions consistent with a renamed column natively;
 *  3. rename the unique index to match (`ow_` -> `qw_` prefix), purely
 *     cosmetic — SQLite compiles this as drop+recreate, MySQL as a native
 *     `RENAME INDEX`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('opportunity_workflow_criteria', 'quote_workflow_criteria');

        Schema::table('quote_workflow_criteria', function (Blueprint $table): void {
            $table->renameColumn('opportunity_workflow_id', 'quote_workflow_id');
        });

        Schema::table('quote_workflow_criteria', function (Blueprint $table): void {
            $table->renameIndex('ow_criteria_workflow_field_unique', 'qw_criteria_workflow_field_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quote_workflow_criteria', function (Blueprint $table): void {
            $table->renameIndex('qw_criteria_workflow_field_unique', 'ow_criteria_workflow_field_unique');
        });

        Schema::table('quote_workflow_criteria', function (Blueprint $table): void {
            $table->renameColumn('quote_workflow_id', 'opportunity_workflow_id');
        });

        Schema::rename('quote_workflow_criteria', 'opportunity_workflow_criteria');
    }
};
