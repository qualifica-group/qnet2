<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-type segnatempo requirement at task completion (spec 0162, D-1):
 * `true` keeps today's behaviour (mandatory `time_entry` on
 * POST /api/tasks/{task}/complete), `false` makes it optional for the Tasks
 * classified under that type. Defaults to `true` so every existing row keeps
 * requiring it — the flag opts a type OUT, never in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_types', function (Blueprint $table) {
            $table->boolean('requires_time_entry')->default(true)->after('is_default');
        });
    }

    public function down(): void
    {
        Schema::table('task_types', function (Blueprint $table) {
            $table->dropColumn('requires_time_entry');
        });
    }
};
