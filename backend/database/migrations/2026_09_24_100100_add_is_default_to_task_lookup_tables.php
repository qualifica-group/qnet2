<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Default row marker for the task type/priority/importance configurators
 * (spec 0154, D-8): the row a new task falls back to when the field is
 * omitted. At most one per table is enforced by the service that sets it,
 * not by the schema (a partial unique index is not portable to SQLite/MySQL).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const array TABLES = ['task_types', 'task_priorities', 'task_importances'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }
};
