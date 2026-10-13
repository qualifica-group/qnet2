<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VAT rate of the agent operations (spec 0213, D-2): null means no VAT
 * (e.g. flat-rate regime).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_fiscal_profiles', function (Blueprint $table) {
            $table->foreignId('vat_rate_id')->nullable()->after('enasarco_mandate_type')->constrained('vat_rates')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('agent_fiscal_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vat_rate_id');
        }));
    }
};
