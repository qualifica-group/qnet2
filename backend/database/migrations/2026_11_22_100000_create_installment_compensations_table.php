<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compensations (spec 0364): an open receivable installment and an open payable
 * installment of the same registry and company closed against each other for the
 * lower residual, with no bank or cash movement. The two installments point back
 * to the compensation through `compensation_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_compensations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('registry_id')->constrained('registries')->restrictOnDelete();
            $table->foreignId('invoice_installment_id')->constrained('invoice_installments')->restrictOnDelete();
            $table->foreignId('purchase_invoice_installment_id')->constrained('purchase_invoice_installments')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('compensated_at');
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['registry_id', 'company_id']);
        });

        foreach (['invoice_installments', 'purchase_invoice_installments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('compensation_id')->nullable()->constrained('installment_compensations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['invoice_installments', 'purchase_invoice_installments'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('compensation_id');
            });
        }

        Schema::dropIfExists('installment_compensations');
    }
};
