<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent document received against a settlement, its approval and the linked
 * purchase invoice (spec 0267, D-1, D-3, D-4). The invoice link is unique: one
 * invoice backs at most one settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_settlements', function (Blueprint $table) {
            $table->string('document_number', 30)->nullable();
            $table->date('document_date')->nullable();
            $table->decimal('document_taxable_amount', 15, 2)->nullable();
            $table->decimal('document_vat_amount', 15, 2)->nullable();
            $table->decimal('document_withholding_amount', 15, 2)->nullable();
            $table->decimal('document_enasarco_amount', 15, 2)->nullable();
            $table->decimal('document_total_amount', 15, 2)->nullable();
            $table->timestamp('document_received_at')->nullable();
            $table->foreignId('document_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->unique()->constrained('purchase_invoices')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('commission_settlements', function (Blueprint $table) {
            $table->dropUnique(['purchase_invoice_id']);
            $table->dropConstrainedForeignId('purchase_invoice_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('document_received_by');
            $table->dropColumn([
                'document_number', 'document_date', 'document_taxable_amount', 'document_vat_amount',
                'document_withholding_amount', 'document_enasarco_amount', 'document_total_amount',
                'document_received_at', 'approved_at',
            ]);
        });
    }
};
