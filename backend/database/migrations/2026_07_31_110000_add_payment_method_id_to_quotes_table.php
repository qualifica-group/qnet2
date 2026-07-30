<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `payment_method_id` on a Quote (user directive 2026-07-30): the payment
 * modality agreed on the offerta, picked from the `payment_methods` lookup
 * (spec 0068) — which until now had no consumer at all.
 *
 * Nullable and `nullOnDelete` for the same reason as `layout_id`: the real
 * protection is the application-level delete-guard in
 * `PaymentMethodService::delete()` (the extension point that file already
 * documented for its first consumer), which returns an informative 409
 * instead of a DB integrity error; `nullOnDelete` is only the schema's own
 * safety net.
 *
 * No explicit index() call: `constrained()` already creates the FK and its
 * index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('layout_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
