<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Active invoicing documents (spec 0194): a proforma that becomes an invoice
 * once the external number is registered (D-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('proforma')->index();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->unsignedSmallInteger('year');
            $table->date('document_date')->index();
            $table->foreignId('proforma_request_id')->nullable()->unique()->constrained('proforma_requests')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('customer_registry_id')->constrained('registries')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->string('external_number', 30)->nullable()->index();
            $table->date('external_date')->nullable();
            $table->text('notes')->nullable();
            $table->text('internal_note')->nullable();
            $table->string('tag', 16)->nullable();
            $table->decimal('deviation', 15, 2)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'year', 'number']);
            $table->index(['year', 'document_date']);
            $table->index(['type', 'document_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
