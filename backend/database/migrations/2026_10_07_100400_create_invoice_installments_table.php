<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installment schedule of an invoice with the collection state (spec 0194, D-13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->date('due_date')->index();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method_code', 32)->nullable();
            $table->decimal('collected_amount', 15, 2)->nullable();
            $table->date('collected_at')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');
    }
};
