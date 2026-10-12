<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * N:N link between payable documents and purchase orders (spec 0211, D-2). An
 * order can be on several documents (partial billing); it cannot be deleted
 * while linked (restrictOnDelete backs the 409 raised by the service).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_invoice_purchase_order', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['purchase_invoice_id', 'purchase_order_id'], 'purchase_invoice_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_purchase_order');
    }
};
