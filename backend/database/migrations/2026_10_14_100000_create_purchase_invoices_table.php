<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal payable document, proforma or invoice from a supplier (spec 0211,
 * D-1). Phase 1 only seeds it: its form and CRUD arrive with the payable
 * invoices module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 16)->default('invoice')->index();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('registries')->restrictOnDelete();
            $table->string('document_number', 30)->nullable();
            $table->date('document_date')->index();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->foreignId('cost_type_id')->nullable()->constrained('cost_types')->nullOnDelete();
            $table->foreignId('company_site_id')->nullable()->constrained('company_sites')->nullOnDelete();
            $table->foreignId('operational_site_id')->nullable()->constrained('operational_sites')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['supplier_id', 'document_number']);
            $table->index(['document_type', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
