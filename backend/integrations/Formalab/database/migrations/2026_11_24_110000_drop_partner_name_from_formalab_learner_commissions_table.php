<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0427 D-2 (revised 2026-11-24): the learner's partner is the "Partner
 * commerciale" of the learner's offer, no longer a name stored per tax code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalab_learner_commissions', function (Blueprint $table): void {
            $table->dropColumn('partner_name');
        });
    }

    public function down(): void
    {
        Schema::table('formalab_learner_commissions', function (Blueprint $table): void {
            $table->string('partner_name')->nullable()->after('tax_code');
        });
    }
};
