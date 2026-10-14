<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase order receipts (spec 0254, D-1, D-10): a receipt is a document of
 * received quantities per order line; `received_quantity` on the line is the
 * denormalised sum, written only by the services under the order lock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->date('receipt_date');
            $table->string('delivery_note_number', 50)->nullable();
            $table->date('delivery_note_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'receipt_date']);
            $table->index('purchase_invoice_id');
        });

        Schema::create('purchase_order_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_receipt_id')->constrained('purchase_order_receipts')->cascadeOnDelete();
            $table->foreignId('purchase_order_line_id')->constrained('purchase_order_lines')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['purchase_order_receipt_id', 'purchase_order_line_id'], 'purchase_order_receipt_lines_receipt_line_unique');
        });

        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('received_quantity', 12, 3)->default(0)->after('quantity');
            $table->timestamp('residual_closed_at')->nullable();
            $table->foreignId('residual_closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('residual_close_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('residual_closed_by_id');
        });
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->dropColumn(['received_quantity', 'residual_closed_at', 'residual_close_reason']);
        });

        Schema::dropIfExists('purchase_order_receipt_lines');
        Schema::dropIfExists('purchase_order_receipts');
    }
};
