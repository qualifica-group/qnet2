<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy active documents (spec 0301 D-7):
 * they come from two legacy tables whose ids overlap (`proformas`,
 * `proforma_ente`), so the anchor is the pair (`old_source`, `old_id`), unique,
 * as on products (spec 0174). Added as its own migration because
 * `create_invoices_table` is already committed (backend.md section 3).
 */
return new class extends Migration
{
    private const string UNIQUE = 'invoices_old_source_old_id_unique';

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('old_source', 32)->nullable()->after('id');
            $table->unsignedBigInteger('old_id')->nullable()->after('old_source');
            $table->unique(['old_source', 'old_id'], self::UNIQUE);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE);
            $table->dropColumn(['old_source', 'old_id']);
        });
    }
};
