<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Write-off (abbuono / stralcio) of the residual of an installment (spec 0361).
 * The generated `residual_amount` also subtracts it; a generated column cannot
 * be altered in place on SQLite, so it is dropped (index first) and re-added
 * under the same index name on both tables.
 */
return new class extends Migration
{
    /** table => [settled column, index name] */
    private const array TABLES = [
        'invoice_installments' => ['collected_amount', 'invoice_installments_residual_due_index'],
        'purchase_invoice_installments' => ['paid_amount', 'purchase_invoice_installments_residual_due_index'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name => [$settledColumn, $index]) {
            Schema::table($name, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('residual_amount');
            });

            Schema::table($name, function (Blueprint $table) use ($settledColumn) {
                $table->decimal('write_off_amount', 15, 2)->nullable();
                $table->string('write_off_reason', 20)->nullable();
                $table->string('write_off_note', 255)->nullable();
                $table->decimal('residual_amount', 15, 2)
                    ->virtualAs("amount - COALESCE({$settledColumn}, 0) - COALESCE(write_off_amount, 0)");
            });

            Schema::table($name, function (Blueprint $table) use ($index) {
                $table->index(['residual_amount', 'due_date'], $index);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name => [$settledColumn, $index]) {
            Schema::table($name, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['residual_amount', 'write_off_amount', 'write_off_reason', 'write_off_note']);
            });

            Schema::table($name, function (Blueprint $table) use ($settledColumn) {
                $table->decimal('residual_amount', 15, 2)->virtualAs("amount - COALESCE({$settledColumn}, 0)");
            });

            Schema::table($name, function (Blueprint $table) use ($index) {
                $table->index(['residual_amount', 'due_date'], $index);
            });
        }
    }
};
