<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase order lines (spec 0210). Amounts are persisted, computed server-side
 * (D-12). `purchase_request_line_id` links the RDA line an order line came from
 * (D-17) and is unique: a request line feeds at most one order line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->foreignId('cost_type_id')->constrained('cost_types')->restrictOnDelete();
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->foreignId('purchase_request_line_id')->nullable()->unique()->constrained('purchase_request_lines')->nullOnDelete();
            $table->timestamps();

            $table->index(['purchase_order_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
