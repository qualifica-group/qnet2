<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The typology keeps only the `supplier_commission_enabled` switch; the
 * direction lives on the commission rule (spec 0306, D-2). The down() restores
 * the column with PAID wherever the switch is on (the pre-0202 behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->dropColumn('supplier_commission_direction');
        });
    }

    public function down(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->string('supplier_commission_direction', 16)->nullable()->after('supplier_commission_enabled');
        });

        DB::table('product_typologies')
            ->where('supplier_commission_enabled', true)
            ->update(['supplier_commission_direction' => 'PAID']);
    }
};
