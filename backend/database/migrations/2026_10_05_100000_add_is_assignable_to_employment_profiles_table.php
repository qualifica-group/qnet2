<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0194: an explicit "Assegnabile" switch on the employment profile, a
 * third blocking condition next to the Sede and the competence. False takes
 * the user out of every assignment pool. Defaults true (D-2), so every
 * existing profile keeps today's behavior.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->boolean('is_assignable')->default(true)->after('covers_all_product_categories');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn('is_assignable');
        });
    }
};
