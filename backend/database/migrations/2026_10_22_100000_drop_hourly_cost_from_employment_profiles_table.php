<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0277 D-4: the hourly cost is derived from the salary of the contract
 * condition in force, so the manual column of spec 0272 (management control)
 * goes. down() restores the empty column, not its former values.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table) {
            $table->dropColumn('hourly_cost');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table) {
            $table->decimal('hourly_cost', 10, 2)->nullable()->after('break_daily_minutes');
        });
    }
};
