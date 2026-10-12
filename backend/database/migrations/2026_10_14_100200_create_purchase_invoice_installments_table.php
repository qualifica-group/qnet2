<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installment schedule of a payable document with the payment state (spec
 * 0211, D-4): mirror of invoice_installments in payment wording. The VIRTUAL
 * `residual_amount` stores nothing, so it can never drift from the two columns
 * it derives from; the status stays derived in code. `redistribution_snapshot`
 * is the plan before a partial payment, so clearing it restores it (D-6).
 */
return new class extends Migration
{
    private const string SEQUENCE_UNIQUE = 'purchase_invoice_installments_sequence_unique';

    private const string RESIDUAL_INDEX = 'purchase_invoice_installments_residual_due_index';

    public function up(): void
    {
        Schema::create('purchase_invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->date('due_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method_code', 32)->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->date('paid_at')->nullable();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->json('redistribution_snapshot')->nullable();
            $table->decimal('residual_amount', 15, 2)->virtualAs('amount - COALESCE(paid_amount, 0)');
            $table->timestamps();

            $table->unique(['purchase_invoice_id', 'sequence'], self::SEQUENCE_UNIQUE);
            $table->index(['residual_amount', 'due_date'], self::RESIDUAL_INDEX);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_installments');
    }
};
