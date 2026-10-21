<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0291 D-3/D-4: working time and contract expiry of a condition, and
 * their copy of the condition in force on the profile. Existing rows stay
 * without them (no backfill).
 */
return new class extends Migration
{
    private const array TABLES = ['employment_contracts', 'employment_profiles'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('working_time')->nullable()->after('contract_type_id');
                $table->date('contract_expires_on')->nullable()->after('working_time');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['working_time', 'contract_expires_on']);
            });
        }
    }
};
