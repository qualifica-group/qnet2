<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0094 (D-1/D-2): the Campaign's business function / product category
 * pair moves from two single columns to a one-to-many collection of rows,
 * mirroring `opportunity_product_lines` (spec 0040 amendment rev.3) exactly.
 * `campaign_id` cascades (a deleted campaign drops its own rows);
 * `business_function_id`/`product_category_id` stay restrictOnDelete,
 * mirroring every other FK on this module (BR-3). A campaign linked to a
 * project (BR-2) never owns rows here: it keeps reading the project's own
 * collection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_product_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_function_id')->constrained('business_functions')->restrictOnDelete();
            $table->foreignId('product_category_id')->constrained('product_categories')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'business_function_id', 'product_category_id'], 'campaign_product_lines_unique_pair');
            $table->index('business_function_id');
            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_product_lines');
    }
};
