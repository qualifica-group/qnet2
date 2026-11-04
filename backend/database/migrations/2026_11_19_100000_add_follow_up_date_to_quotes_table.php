<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Data ricontatto" of the offer (spec 0349): an optional date, edited from
 * the quote record and the quotes grid. Indexed: the grid sorts and filters it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->date('follow_up_date')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropIndex(['follow_up_date']);
            $table->dropColumn('follow_up_date');
        });
    }
};
