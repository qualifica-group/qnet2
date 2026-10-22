<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy proforma requests (spec 0300): the
 * nullable + unique `old_id` the import engine reads for idempotence, and that
 * the active invoice source links a document to its request through (spec
 * 0301). Added as its own migration because `create_proforma_requests_table`
 * is already committed (backend.md section 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proforma_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('proforma_requests', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
