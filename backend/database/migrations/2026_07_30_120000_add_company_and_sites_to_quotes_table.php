<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Societa' (`company_id`), Societa' Sede (`company_site_id`) and Sede
 * operativa (`operational_site_id`) on a Quote (user directive 2026-07-30).
 *
 * All three are OPTIONAL and nullOnDelete — a deliberate deviation from this
 * table's other restrictOnDelete FKs (`opportunity_id`, the 3 role
 * snapshots), for the same reason spec 0056 gave the Opportunity's own
 * `operational_site_id` (BR-3): removing a company or a site from its
 * catalogue must never be blocked by, nor delete, a quote — the quote simply
 * loses the reference.
 *
 * `operational_site_id` is prefilled from the quote's Opportunity at creation
 * (QuoteService::applySnapshotDefaults) exactly like the 3 commercial roles
 * (D-3): a SNAPSHOT, freely editable afterwards, never a live read-through.
 * `company_id`/`company_site_id` have no Opportunity counterpart (removed
 * there by the 2026-07-17 directive), so they are always picked by hand.
 *
 * No explicit index() call: `constrained()` already creates the FK, which
 * carries the index the grid's filter/sort/distinct-values subqueries read
 * (all three are SSRM table columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('supervisor_id')
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('company_site_id')
                ->nullable()
                ->after('company_id')
                ->constrained('company_sites')
                ->nullOnDelete();

            $table->foreignId('operational_site_id')
                ->nullable()
                ->after('company_site_id')
                ->constrained('operational_sites')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_site_id');
            $table->dropConstrainedForeignId('company_site_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
