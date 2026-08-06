<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0083 (D-1/D-8): the Offerta's own status stops being a flat pick from
 * `quote_statuses` (spec 0065) and becomes a row of the criteria-resolved
 * `quote_workflow_statuses` set — the same configurator the Opportunity used
 * to own (spec 0047), now renamed and re-targeted at the Quote.
 *
 * No data migration (D-4): every EXISTING Quote is backfilled onto the
 * `system_key = 'open'` row of the GLOBAL default set
 * (`quote_workflow_id IS NULL`) — a real, user-configurable row, never a
 * hardcoded string — before the column is tightened to NOT NULL. Split into
 * four phases (add nullable -> backfill -> tighten -> index), the same
 * discipline as `2026_07_29_100000_add_code_to_products_table`: the ALTER
 * that adds the NOT NULL constraint must never run before every row already
 * satisfies it.
 *
 * `down()` restores the STRUCTURE only, NULLABLE (the original NOT NULL
 * cannot be honoured without inventing values, same precedent as
 * `2026_08_05_110000_drop_opportunity_status_id_from_opportunities_table`):
 * DESTRUCTIVE, the per-quote status assignment made under the new set is not
 * recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['quote_status_id']);
            $table->dropIndex(['quote_status_id']);
            $table->dropColumn('quote_status_id');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('quote_workflow_status_id')->nullable()
                ->after('opportunity_id')
                ->constrained('quote_workflow_statuses')
                ->restrictOnDelete();
        });

        $this->backfillWithGlobalOpenStatus();

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('quote_workflow_status_id')->nullable(false)->change();
            $table->index('quote_workflow_status_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['quote_workflow_status_id']);
            $table->dropIndex(['quote_workflow_status_id']);
            $table->dropColumn('quote_workflow_status_id');
        });

        Schema::table('quotes', function (Blueprint $table): void {
            $table->foreignId('quote_status_id')->nullable()
                ->after('opportunity_id')
                ->constrained('quote_statuses')
                ->restrictOnDelete();
            $table->index('quote_status_id');
        });
    }

    private function backfillWithGlobalOpenStatus(): void
    {
        $globalOpenId = DB::table('quote_workflow_statuses')
            ->whereNull('quote_workflow_id')
            ->where('system_key', 'open')
            ->value('id');

        if ($globalOpenId === null) {
            throw new RuntimeException(
                'Global default quote workflow status set is missing its "open" row; '
                .'cannot backfill quotes.quote_workflow_status_id.'
            );
        }

        DB::table('quotes')->update(['quote_workflow_status_id' => $globalOpenId]);
    }
};
