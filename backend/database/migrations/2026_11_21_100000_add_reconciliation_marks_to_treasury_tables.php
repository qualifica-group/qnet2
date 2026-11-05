<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliation marks (spec 0363): a row of an account statement is ticked when
 * found on the bank statement. A transfer shows on two statements, so a movement
 * has one mark per side (`counterpart_*` for the destination account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->timestamp('reconciled_at')->nullable();
            $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counterpart_reconciled_at')->nullable();
            $table->foreignId('counterpart_reconciled_by')->nullable()->constrained('users')->nullOnDelete();
        });

        foreach (['invoice_installments', 'purchase_invoice_installments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('reconciled_at')->nullable();
                $table->foreignId('reconciled_by')->nullable()->constrained('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoice_installments', 'purchase_invoice_installments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('reconciled_by');
                $table->dropColumn('reconciled_at');
            });
        }

        Schema::table('treasury_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counterpart_reconciled_by');
            $table->dropColumn('counterpart_reconciled_at');
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn('reconciled_at');
        });
    }
};
