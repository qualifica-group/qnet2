<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product entity (spec 0017): `category_id` restricts on delete — a category
 * with products cannot be removed (ProductCategoryService also guards this at
 * the service level before the FK is even reached). Every other reference
 * (`vat_rate_id`, `supplier_id`, `state_id`) is optional and nullOnDelete: a
 * product never restricts removal of its VAT rate, supplier or Region.
 *
 * `attribute_values` (spec 0061) holds the dynamic-field values keyed by
 * Attribute `code` — the PRODUCT-context effective attributes of the product's
 * own category (App\Products\ProductAttributeResolver). Written EXCLUSIVELY by
 * ProductService, never mass-assignable (deliberately absent from Product's
 * #[Fillable]).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // The external system's id for a row migrated from it (spec 0013):
            // NULL for native qnet rows, unique among migrated ones.
            $table->unsignedBigInteger('old_id')->nullable()->unique();

            $table->string('name', 191);
            $table->text('description')->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->decimal('price', 15, 2)->nullable();
            $table->foreignId('vat_rate_id')->nullable()->constrained('vat_rates')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('registries')->nullOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->nullOnDelete();
            $table->json('attribute_values')->nullable();
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();

            // Classification backed by App\Enums\ProductType; NOT NULL with a
            // SERVICE default (the only value the catalogue currently exposes).
            $table->string('product_type', 32)->default('SERVICE');

            $table->timestamps();

            $table->index('name');
            $table->index('category_id');
            $table->index('created_at');
            $table->index('product_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
