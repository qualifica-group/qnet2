<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0040, amendment rev.3: the business function and product category of an
 * Opportunity are a one-to-many collection of rows, not a single column pair on
 * `opportunities` — the same business function may repeat across rows paired
 * with a different product category, but the exact pair is unique.
 * `opportunity_id` cascades (a deleted opportunity drops its own rows);
 * `business_function_id`/`product_category_id` stay restrictOnDelete,
 * mirroring every other FK on this module (BR-3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('opportunity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['opportunity_id', 'business_function_id', 'product_category_id'], 'opportunity_product_lines_unique_pair');
            $table->index('business_function_id');
            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_product_lines');
    }
};
