<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0332 D-2: the opportunity's Fonte moves onto its FIRST offer (lowest
 * quote id); sibling offers stay without it and an opportunity with no offers
 * loses it. Set-based statements (tens of thousands of rows), valid on both
 * MySQL and SQLite. The derived table around MIN(id) sidesteps MySQL's
 * "target table in subquery" restriction.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'UPDATE quotes SET source_id = (SELECT o.source_id FROM opportunities o WHERE o.id = quotes.opportunity_id)
             WHERE id IN (SELECT first_quote_id FROM (SELECT MIN(id) AS first_quote_id FROM quotes GROUP BY opportunity_id) first_quotes)'
        );

        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
        });
    }

    /**
     * Restores the column and refills it from each opportunity's first offer
     * (the only offer that carries the Fonte after `up()`).
     */
    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->after('supervisor_id')->constrained('sources')->restrictOnDelete();
        });

        DB::statement(
            'UPDATE opportunities SET source_id = (
                SELECT q.source_id FROM quotes q
                WHERE q.id = (SELECT MIN(q2.id) FROM quotes q2 WHERE q2.opportunity_id = opportunities.id)
            )'
        );
    }
};
