<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-2): the Opportunity loses its own working-status pick
 * entirely — `App\Services\Opportunities\OpportunityStatusResolver`'s
 * `SOURCE_WORKFLOW` fallback disappears along with this column, replaced by
 * the GLOBAL default set's `open` row (D-8) when the Opportunity has no
 * Quote. At this point in the migration chain the FK target
 * (`opportunity_workflow_statuses`, spec 0047) has already been renamed to
 * `quote_workflow_statuses` by
 * `2026_08_05_120200_rename_opportunity_workflow_statuses_to_quote_workflow_statuses_table`.
 *
 * DESTRUCTIVE: the per-opportunity working-status assignment is not
 * recoverable. `down()` restores the STRUCTURE only, same precedent as
 * `2026_08_05_110000_drop_opportunity_status_id_from_opportunities_table`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['opportunity_workflow_status_id']);
            $table->dropColumn('opportunity_workflow_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->foreignId('opportunity_workflow_status_id')->nullable()
                ->after('state_id')
                ->constrained('quote_workflow_statuses')
                ->nullOnDelete();
        });
    }
};
