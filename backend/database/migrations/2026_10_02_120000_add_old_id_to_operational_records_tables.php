<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy operational records (spec 0189):
 * anagrafiche, opportunita', offerte with their lines, commesse. The nullable +
 * unique `old_id` is what the import engine reads for idempotence and for the
 * relational remap down the chain registry <- opportunity <- quote <- work
 * order. `quote_lines` carries one too: a legacy commessa links its offer
 * lines by their legacy id (`orderservices.quotation_service`). Same pattern as
 * `2026_09_28_100100_add_old_id_to_task_templates_table`.
 */
return new class extends Migration
{
    private const array TABLES = ['registries', 'opportunities', 'quotes', 'quote_lines', 'work_orders'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropUnique(['old_id']);
                $table->dropColumn('old_id');
            });
        }
    }
};
