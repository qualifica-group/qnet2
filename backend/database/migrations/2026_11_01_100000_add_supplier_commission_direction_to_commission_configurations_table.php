<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The direction of the Supplier commission (RECEIVED|PAID) moves onto the rule
 * (spec 0306, D-1/D-4): required for `recipient_role` = SUPPLIER, null for every
 * other role. Every existing Supplier rule is backfilled PAID; no quote line
 * snapshot and no commission amount is touched (D-5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->string('supplier_commission_direction', 16)->nullable()->after('commission_type');
        });

        DB::table('commission_configurations')
            ->where('recipient_role', 'SUPPLIER')
            ->update(['supplier_commission_direction' => 'PAID']);
    }

    public function down(): void
    {
        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->dropColumn('supplier_commission_direction');
        });
    }
};
