<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proforma requests (spec 0193): what a work order asks Accounting to invoice.
 * One row per consultancy bundle and per institution supplier. `payment_method_id`
 * is a snapshot of the offer's method at request time (D-9); `issued_at` is set
 * by the future invoicing flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proforma_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('issued_at')->nullable();
            $table->text('note');
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['work_order_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_requests');
    }
};
