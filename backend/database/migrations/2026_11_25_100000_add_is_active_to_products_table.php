<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product "Attivo" flag (spec 0366). Default true; backfilled once to
 * false on the catalogue migrated from the legacy QNet, which marks every such
 * name with the "[OLD] " prefix (D-3). From now on the legacy sends the flag.
 */
return new class extends Migration
{
    private const string LEGACY_NAME_PATTERN = '[OLD] %';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });

        DB::table('products')->where('name', 'like', self::LEGACY_NAME_PATTERN)->update(['is_active' => false]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
