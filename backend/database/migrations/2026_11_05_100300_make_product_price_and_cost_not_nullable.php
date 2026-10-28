<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0320 rev. 3, D-20: every product has a standard price and a standard
 * cost whatever its usages, 0 when not given — the columns become NOT NULL
 * default 0, any NULL left becomes 0 first.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereNull('price')->update(['price' => 0]);
        DB::table('products')->whereNull('cost')->update(['cost' => 0]);

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->change();
            $table->decimal('cost', 15, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->nullable()->default(null)->change();
            $table->decimal('cost', 15, 2)->nullable()->default(null)->change();
        });
    }
};
