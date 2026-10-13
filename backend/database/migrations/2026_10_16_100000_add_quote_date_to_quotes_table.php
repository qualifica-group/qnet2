<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Data offerta" (spec 0228): the offer's own date, always set. Existing
 * offers take the date of their `created_at` (AC-003), the same default a new
 * offer gets when none is submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->date('quote_date')->nullable()->after('title_is_manual');
        });

        DB::table('quotes')->update(['quote_date' => DB::raw('DATE(created_at)')]);
        DB::table('quotes')->whereNull('quote_date')->update(['quote_date' => DB::raw('CURRENT_DATE')]);

        Schema::table('quotes', function (Blueprint $table): void {
            $table->date('quote_date')->nullable(false)->change();
            $table->index('quote_date');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropIndex(['quote_date']);
            $table->dropColumn('quote_date');
        });
    }
};
