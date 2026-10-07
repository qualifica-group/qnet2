<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy commission rules (spec 0203): the
 * nullable + unique `old_id` is what the `commission-configurations` source
 * reads for idempotence. Same pattern as
 * `2026_10_02_120000_add_old_id_to_operational_records_tables`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('commission_configurations', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
