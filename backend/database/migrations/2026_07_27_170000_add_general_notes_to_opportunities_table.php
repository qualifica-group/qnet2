<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Note generali" (user directive 2026-07-27): a free-text field on the
 * opportunity, inherited from the originating Lead's own `notes` at
 * conversion (LeadOpportunityDefaultsResolver, a plain default — never
 * BR-2-locked).
 *
 * Deliberately NOT named `notes`: `Opportunity` already carries a `notes()`
 * morphMany (the collaborative thread, spec 0052 / HasNotes), which a column
 * of that name would shadow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->text('general_notes')->nullable()->after('success_probability');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn('general_notes');
        });
    }
};
