<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0304 D-12: a null `annual_minutes` means "use the default of the type".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_allowances', function (Blueprint $table): void {
            $table->unsignedInteger('annual_minutes')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('absence_allowances')->whereNull('annual_minutes')->update(['annual_minutes' => 0]);

        Schema::table('absence_allowances', function (Blueprint $table): void {
            $table->unsignedInteger('annual_minutes')->default(0)->nullable(false)->change();
        });
    }
};
