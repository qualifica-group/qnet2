<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text "Note generali" of the anagrafica (spec 0207), shown in the same
 * amber callout the request work panel uses for the opportunity's notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->text('general_notes')->nullable()->after('agreement_notes');
        });
    }

    public function down(): void
    {
        Schema::table('registries', function (Blueprint $table) {
            $table->dropColumn('general_notes');
        });
    }
};
