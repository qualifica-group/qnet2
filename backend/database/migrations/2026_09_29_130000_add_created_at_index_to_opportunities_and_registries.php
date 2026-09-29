<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `created_at` is the default sort of the Opportunita' and Anagrafiche grids
 * (and of their monthly trend widget), yet unlike `quotes.created_at` it had
 * no index: on 1M rows every first page was a full filesort. Stress test
 * 2026-09-29.
 */
return new class extends Migration
{
    private const array TABLES = ['opportunities', 'registries'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->index('created_at');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropIndex(['created_at']);
            });
        }
    }
};
