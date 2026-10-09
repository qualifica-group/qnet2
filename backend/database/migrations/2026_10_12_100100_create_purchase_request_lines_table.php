<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase request lines (spec 0208): every line has its own approval and
 * fulfilment state. Amounts are persisted, computed server-side (D-12).
 * `oda_reference` is a read-only placeholder until the ODA module exists (D-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 500);
            $table->text('reason')->nullable();
            $table->foreignId('unit_of_measure_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 14, 2);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->decimal('taxable_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('pending_approval')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('oda_reference', 50)->nullable();
            $table->timestamps();

            $table->index(['purchase_request_id', 'position']);
            $table->index(['purchase_request_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_lines');
    }
};
