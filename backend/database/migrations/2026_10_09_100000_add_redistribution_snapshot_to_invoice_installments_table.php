<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan of the installments before a partial collection redistributed the
 * residual, so clearing that collection can restore it exactly (spec 0196, D-12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->json('redistribution_snapshot')->nullable()->after('collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->dropColumn('redistribution_snapshot');
        });
    }
};
