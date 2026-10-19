<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0272 D-3: the CCNL level replaces the qualification type. Existing
 * values are discarded (none in use).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn('qualification_type');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->string('qualification_type')->nullable();
        });
    }
};
