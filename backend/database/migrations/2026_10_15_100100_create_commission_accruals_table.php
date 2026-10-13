<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commission accrual ledger (spec 0212): one ACCRUAL/REVERSAL row per
 * recipient and invoice line. The recipient is polymorphic by plain columns
 * (type + id), so there is no FK on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('quote_line_id')->nullable()->constrained('quote_lines')->nullOnDelete();
            $table->foreignId('quote_line_commission_id')->nullable()->constrained('quote_line_commissions')->nullOnDelete();
            $table->foreignId('invoice_installment_id')->nullable()->constrained('invoice_installments')->nullOnDelete();
            $table->string('recipient_role', 32);
            $table->string('recipient_type', 32);
            $table->unsignedBigInteger('recipient_id');
            $table->string('type', 16);
            $table->decimal('base_amount', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->date('accrued_on');
            $table->date('settled_externally_on')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'quote_line_id', 'recipient_role'], 'commission_accruals_bucket_index');
            $table->index(['recipient_type', 'recipient_id', 'accrued_on'], 'commission_accruals_recipient_index');
            $table->index('accrued_on', 'commission_accruals_accrued_on_index');
            $table->index('settled_externally_on', 'commission_accruals_settled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_accruals');
    }
};
