<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy supplier invoices (spec 0298): the
 * nullable + unique `old_id` (the lowest FTP id of the merged group) the import
 * engine reads for idempotence. Added as its own migration because
 * `create_purchase_invoices_table` is already committed (backend.md section 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
