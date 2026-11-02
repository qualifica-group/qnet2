<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Incremental legacy import (spec 0345): the day (Y-m-d) from which the legacy
 * is asked for created/modified rows only. Null = full import.
 */
return new class extends Migration
{
    private const array TABLES = ['migration_runs', 'mass_migration_runs'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->date('updated_since')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('updated_since');
            });
        }
    }
};
