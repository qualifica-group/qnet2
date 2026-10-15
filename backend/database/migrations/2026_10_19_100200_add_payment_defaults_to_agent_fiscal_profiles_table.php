<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default payment method and cost type proposed when a settlement is approved
 * (spec 0267, D-8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_fiscal_profiles', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_fiscal_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropConstrainedForeignId('cost_type_id');
        });
    }
};
