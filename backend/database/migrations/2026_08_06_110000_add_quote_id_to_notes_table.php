<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote-scoped notes (spec 0085, D-1): `notes.quote_id` is a nullable
 * scoping column, NOT a second polymorphic `notable` — the note stays
 * appended to its Opportunity (`notable_type`/`notable_id`, unchanged).
 * Null means a GENERAL note (the whole Opportunity), a value means the note
 * belongs to that Offerta.
 *
 * cascadeOnDelete (AC-002): deleting an Offerta removes its own notes along
 * with it; the Opportunity's general notes (`quote_id IS NULL`) are
 * untouched since they never reference the row being deleted.
 *
 * The composite index matches the ROOT-note query shape (D-2): filtering by
 * `notable_type`/`notable_id` (host), then `quote_id` (the `quote_scope`
 * predicate), ordered by `created_at` for the keyset page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('quote_id')->nullable()->after('notable_id')->constrained('quotes')->cascadeOnDelete();

            $table->index(['notable_type', 'notable_id', 'quote_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropIndex(['notable_type', 'notable_id', 'quote_id', 'created_at']);
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
