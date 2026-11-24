<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0427 D-2 rev.: the learner's partner is the "Partner commerciale"
 * referent of the learner's registry and its supplier the referent's own
 * (`referents.supplier_registry_id`), no longer a hand-written name and a
 * supplier stored per learner. Only the commission stays here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('formalab_learner_commissions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_registry_id');
            $table->dropColumn('partner_name');
        });
    }

    public function down(): void
    {
        Schema::table('formalab_learner_commissions', function (Blueprint $table): void {
            $table->string('partner_name')->nullable()->after('tax_code');
            $table->foreignId('supplier_registry_id')->nullable()->after('amount')->constrained('registries')->nullOnDelete();
        });
    }
};
