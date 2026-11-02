<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for segnatempo (spec 0341 D-7): the nullable +
 * unique `old_id` the import engine reads for idempotence - the legacy
 * `working_activity_intervals` id on `time_entries`, the legacy
 * `work_activity_day_notes` id on `time_entry_day_notes`. Added as its own
 * migration because both tables are already committed (backend.md §3).
 */
return new class extends Migration
{
    private const array TABLES = ['time_entries', 'time_entry_day_notes'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['old_id']);
                $table->dropColumn('old_id');
            });
        }
    }
};
