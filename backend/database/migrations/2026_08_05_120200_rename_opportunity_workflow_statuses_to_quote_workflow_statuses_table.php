<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-6/D-8): same rename as
 * `2026_08_05_120100_rename_opportunity_workflow_criteria_to_quote_workflow_criteria_table`,
 * applied to the status rows. `quote_workflow_id` stays NULLABLE — the
 * GLOBAL default set (`quote_workflow_id IS NULL`) keeps its meaning
 * unchanged (D-8: the Opportunity's zero-Quote fallback reads its `open`
 * row).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('opportunity_workflow_statuses', 'quote_workflow_statuses');

        Schema::table('quote_workflow_statuses', function (Blueprint $table): void {
            $table->renameColumn('opportunity_workflow_id', 'quote_workflow_id');
        });

        Schema::table('quote_workflow_statuses', function (Blueprint $table): void {
            $table->renameIndex('ow_statuses_workflow_name_unique', 'qw_statuses_workflow_name_unique');
            $table->renameIndex('ow_statuses_workflow_system_key_unique', 'qw_statuses_workflow_system_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quote_workflow_statuses', function (Blueprint $table): void {
            $table->renameIndex('qw_statuses_workflow_name_unique', 'ow_statuses_workflow_name_unique');
            $table->renameIndex('qw_statuses_workflow_system_key_unique', 'ow_statuses_workflow_system_key_unique');
        });

        Schema::table('quote_workflow_statuses', function (Blueprint $table): void {
            $table->renameColumn('quote_workflow_id', 'opportunity_workflow_id');
        });

        Schema::rename('quote_workflow_statuses', 'opportunity_workflow_statuses');
    }
};
