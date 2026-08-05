<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `code` is the ONLY unique field of a payment method (user directive
 * 2026-08-05). The name is a plain label: the legacy catalogues the
 * `payment-methods` migration source imports do carry homonymous modalities
 * (same wording, different terms), and refusing them would drop real rows.
 * The unique index becomes a plain one — the column is still searched,
 * filtered and sorted on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->unique('name');
        });
    }
};
