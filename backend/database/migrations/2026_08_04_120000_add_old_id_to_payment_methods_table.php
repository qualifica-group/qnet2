<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchor for the payment method lookup (spec 0013 on spec
 * 0068): the nullable + unique `old_id` the import engine reads for
 * idempotence (a row already imported is skipped, never duplicated) and for
 * relational remapping by the modules that reference a payment method. Added
 * as its own migration because `create_payment_methods_table` is already
 * committed (backend.md §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
