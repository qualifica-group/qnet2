<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-category visual layout for an attribute form/detail (spec 0062, D1/D3):
 * one row per (product_category_id, context, form_mode) — a category has, at
 * most, one configured layout PER usage context (product/opportunity) AND
 * PER form lifecycle stage (create/edit/view), independent of one another.
 * `layout` is the JSON blob (layout-contract, sections -> rows -> items),
 * always allow-list validated against the category's effective attributes
 * before it is written (App\Services\ProductCategories\AttributeLayoutValidator)
 * — never trusted raw. Nullable: a row is only ever inserted once a layout is
 * actually configured; an absent row means "fall back to flat rendering"
 * (backward-compat, AC-007), never a NULL `layout` value at rest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_layouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('context');
            $table->string('form_mode');
            $table->json('layout')->nullable();
            $table->timestamps();

            $table->unique(['product_category_id', 'context', 'form_mode'], 'attribute_layouts_category_context_mode_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_layouts');
    }
};
