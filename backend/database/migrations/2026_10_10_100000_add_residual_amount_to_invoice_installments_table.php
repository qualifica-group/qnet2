<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generated `residual_amount` (amount minus what was collected) and the
 * composite index the installments module filters, sorts and aggregates on
 * (spec 0197, D-2). VIRTUAL: nothing is stored, so it can never drift from
 * the two columns it derives from; the status stays derived in code.
 */
return new class extends Migration
{
    private const string INDEX = 'invoice_installments_residual_due_index';

    public function up(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->decimal('residual_amount', 15, 2)->virtualAs('amount - COALESCE(collected_amount, 0)');
        });

        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->index(['residual_amount', 'due_date'], self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });

        Schema::table('invoice_installments', function (Blueprint $table) {
            $table->dropColumn('residual_amount');
        });
    }
};
