<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0290 D-3/D-4: the contract type of a condition, and its copy of the
 * condition in force on the profile. RESTRICT: a referenced catalogue row
 * cannot vanish. Existing rows stay without a type (no backfill).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->foreignId('contract_type_id')->nullable()->after('relationship_type')
                ->constrained()
                ->restrictOnDelete();
        });

        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->foreignId('contract_type_id')->nullable()->after('relationship_type')
                ->constrained()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_type_id');
        });

        Schema::table('employment_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_type_id');
        });
    }
};
