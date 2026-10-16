<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hourly cost of the person in euro (spec 0272, D-1): values the time entries
 * in the management control reports. No history: the value applies from when
 * it is entered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table) {
            $table->decimal('hourly_cost', 10, 2)->nullable()->after('break_daily_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table) {
            $table->dropColumn('hourly_cost');
        });
    }
};
