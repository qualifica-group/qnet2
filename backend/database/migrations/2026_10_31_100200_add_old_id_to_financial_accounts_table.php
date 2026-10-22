<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the legacy financial accounts (spec 0297): the
 * nullable + unique `old_id` the import engine reads for idempotence, and that
 * the invoice and installment sources will remap the legacy account through.
 * Added as its own migration because `create_financial_accounts_table` is
 * already committed (backend.md section 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('financial_accounts', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
