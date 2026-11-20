<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Withdraws `quote_workflow_statuses.report_columns` (user decision
 * 2026-11-11: the per-status statistics columns will be redesigned later).
 * Some installations already ran the migration that added it; the others
 * never had it, hence the guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quote_workflow_statuses', 'report_columns')) {
            return;
        }

        Schema::table('quote_workflow_statuses', function (Blueprint $table): void {
            $table->dropColumn('report_columns');
        });
    }

    public function down(): void
    {
        // Nothing to restore: the column is not part of the schema any more.
    }
};
