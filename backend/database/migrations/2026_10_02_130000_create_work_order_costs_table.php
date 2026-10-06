<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Actual costs of a commessa (spec 0190): the "Costi effettivi" the
 * "Costi" section compares against the offer's COST lines. Same amount
 * columns as `quote_lines` (net/vat/total computed server-side, unit of
 * measure frozen from the product), plus the cost's own date, supplier and
 * document reference. `quote_line_id` optionally imputes the cost to one of
 * the commessa's REVENUE lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('quote_line_id')->nullable()->constrained('quote_lines')->nullOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('unit_price', 15, 2);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->date('incurred_on');
            $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->string('document_reference', 100)->nullable();
            $table->text('additional_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['work_order_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_costs');
    }
};
