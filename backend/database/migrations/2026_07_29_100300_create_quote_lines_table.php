<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quote line entity (spec 0065, D-11): revenue lines and cost lines share ONE
 * table discriminated by `line_type` (`App\Enums\QuoteLineType`: REVENUE|COST)
 * instead of two parallel tables — same columns, same validator, same FE
 * component on both tabs. `quote_id` cascades (D-8: a deleted Quote drops its
 * own lines together with the header, full-replace happens inside the same
 * transaction), while `product_id`/`vat_rate_id` restrict on delete: a
 * Product or VAT rate still referenced by any line cannot be removed
 * (AC-026/AC-027 mirror this at the Opportunity level).
 *
 * The line stores ONLY `product_id` (D-7): code, name, category and business
 * function are read live from the Product at response time, never
 * duplicated. `vat_rate_id` is nullable — precompiled from
 * `products.vat_rate_id` but editable, and may be cleared to zero-rate.
 *
 * `net_amount`/`vat_amount`/`total_amount` ARE persisted and frozen on the
 * line (D-10/D-12): they are computed server-side at write time from
 * `quantity` * `unit_price` (+ the VAT rate snapshot at that moment) and
 * rounded half-up to 2 decimals, so a later change to `vat_rates.rate` never
 * alters an already-saved Quote. The composite index
 * `(quote_id, line_type, sort_order)` matches the exact read/order path used
 * to render a Quote's Offerta/Costi tabs (lines fetched per quote, split by
 * type, ordered by `sort_order`); `product_id` gets its own index for the
 * FK's own lookups (product detail / for-select consumers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->string('line_type', 16);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->restrictOnDelete();
            $table->decimal('net_amount', 15, 2);
            $table->decimal('vat_amount', 15, 2);
            $table->decimal('total_amount', 15, 2);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quote_id', 'line_type', 'sort_order'], 'quote_lines_quote_type_sort_index');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
